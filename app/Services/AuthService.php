<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PinResetMail;
use App\Mail\TwoFactorMail;
use App\Models\PinReset;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthService
{
    protected TotpService $totpService;

    public function __construct(?TotpService $totpService = null)
    {
        $this->totpService = $totpService ?? new TotpService();
    }

    /**
     * Issue both an access token and a refresh token for the user.
     */
    public function issueTokens(User $user, string $deviceName = 'mobile-app'): array
    {
        // Access token expires in 2 hours
        $accessToken = $user->createToken(
            $deviceName . '-access',
            ['access-api', '*'],
            now()->addHours(2)
        )->plainTextToken;

        // Refresh token expires in 30 days
        $refreshToken = $user->createToken(
            $deviceName . '-refresh',
            ['issue-access-token'],
            now()->addDays(30)
        )->plainTextToken;

        return [
            'token' => $accessToken,
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => 7200,
        ];
    }

    /**
     * Register a new user with name and phone number.
     */
    public function register(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'phone_number' => $data['phone_number'],
            'default_currency' => $data['currency'] ?? 'NGN',
        ]);

        $tokens = $this->issueTokens($user, $data['device_name'] ?? 'mobile-app');

        return array_merge([
            'user' => $user,
        ], $tokens);
    }

    /**
     * Authenticate user with phone number and PIN.
     *
     * @throws ValidationException
     */
    public function login(array $data): array
    {
        $user = User::where('phone_number', $data['phone_number'])->first();

        if (! $user || ! $user->login_pin_hash || ! Hash::check($data['pin'], $user->login_pin_hash)) {
            throw ValidationException::withMessages([
                'phone_number' => ['The provided phone number or PIN is incorrect.'],
            ]);
        }

        // If Two-Factor Authentication is enabled, issue a 2FA challenge
        if ($user->two_factor_enabled) {
            $method = $user->two_factor_method ?: 'authenticator';
            $twoFactorToken = Str::random(64);

            if ($method === 'authenticator' && empty($user->two_factor_secret)) {
                $user->update(['two_factor_secret' => $this->totpService->generateSecret()]);
                $user->refresh();
            }

            $code = null;
            $debugCode = null;

            if ($method === 'authenticator') {
                $debugCode = (app()->environment('local', 'testing') && $user->two_factor_secret)
                    ? $this->totpService->getCode($user->two_factor_secret)
                    : null;

                $user->update([
                    'two_factor_token' => $twoFactorToken,
                    'two_factor_expires_at' => now()->addMinutes(10),
                ]);
            } else {
                $code = (string) random_int(100000, 999999);
                $debugCode = app()->environment('local', 'testing') ? $code : null;

                $user->update([
                    'two_factor_code' => $code,
                    'two_factor_token' => $twoFactorToken,
                    'two_factor_expires_at' => now()->addMinutes(10),
                ]);

                if ($method === 'email' && !empty($user->email)) {
                    try {
                        Mail::to($user->email)->send(new TwoFactorMail($user, $code));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }

            $availableMethods = ['authenticator'];
            if (!empty($user->email)) {
                $availableMethods[] = 'email';
            }
            if (!empty($user->phone_number)) {
                $availableMethods[] = 'phone';
            }

            NotificationService::log(
                user: $user,
                title: 'Two-Factor Challenge 🛡️',
                message: "A 2FA challenge ({$method}) was presented for your account sign-in.",
                category: 'system',
                actionRoute: '/settings',
            );

            return [
                'requires_2fa' => true,
                'two_factor_token' => $twoFactorToken,
                'method' => $method,
                'default_method' => $method,
                'available_methods' => $availableMethods,
                'phone_number' => $user->phone_number,
                'masked_phone' => $this->maskPhone($user->phone_number),
                'email' => $this->maskEmail($user->email),
                'message' => $method === 'authenticator'
                    ? 'Enter the 6-digit code from your Authenticator app.'
                    : ($method === 'email'
                        ? 'A verification code has been sent to your registered email.'
                        : 'A verification code has been sent to your phone number.'),
                'debug_code' => $debugCode,
            ];
        }

        $tokens = $this->issueTokens($user, $data['device_name'] ?? 'mobile-app');

        return array_merge([
            'user' => $user,
        ], $tokens);
    }

    /**
     * Authenticate or register user via Google ID token.
     *
     * The mobile app sends the Google ID token obtained from Google Sign-In SDK.
     * We verify it with Google's tokeninfo endpoint and create/find the user.
     */
    public function googleAuth(string $idToken, string $deviceName = 'mobile-app'): array
    {
        $googleUser = $this->verifyGoogleToken($idToken);

        $user = User::updateOrCreate(
            ['google_id' => $googleUser['sub']],
            [
                'name' => $googleUser['name'] ?? $googleUser['email'],
                'email' => $googleUser['email'],
                'avatar_url' => $googleUser['picture'] ?? null,
                'email_verified_at' => now(),
            ]
        );

        $tokens = $this->issueTokens($user, $deviceName);

        return array_merge([
            'user' => $user,
        ], $tokens);
    }

    /**
     * Refresh access token using a valid refresh token.
     *
     * @throws ValidationException
     */
    public function refreshToken(string $refreshTokenString, string $deviceName = 'mobile-app'): array
    {
        $tokenModel = PersonalAccessToken::findToken($refreshTokenString);

        if (! $tokenModel) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The provided refresh token is invalid.'],
            ]);
        }

        if ($tokenModel->expires_at && $tokenModel->expires_at->isPast()) {
            $tokenModel->delete();
            throw ValidationException::withMessages([
                'refresh_token' => ['The provided refresh token has expired. Please log in again.'],
            ]);
        }

        if (! $tokenModel->can('issue-access-token') && ! str_contains($tokenModel->name, 'refresh')) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The provided token is not a valid refresh token.'],
            ]);
        }

        /** @var User|null $user */
        $user = $tokenModel->tokenable;

        if (! $user) {
            throw ValidationException::withMessages([
                'refresh_token' => ['User associated with token not found.'],
            ]);
        }

        // Rotate refresh token
        $tokenModel->delete();

        $tokens = $this->issueTokens($user, $deviceName);

        return array_merge([
            'user' => $user,
        ], $tokens);
    }

    /**
     * Revoke tokens and logout.
     */
    public function logout(User $user, ?string $refreshTokenString = null, bool $allDevices = false): void
    {
        if ($allDevices) {
            $user->tokens()->delete();
            return;
        }

        $currentToken = $user->currentAccessToken();
        if ($currentToken) {
            $currentToken->delete();
        }

        if ($refreshTokenString) {
            $rtModel = PersonalAccessToken::findToken($refreshTokenString);
            if ($rtModel && $rtModel->tokenable_id === $user->id) {
                $rtModel->delete();
            }
        } else {
            // Delete all refresh tokens for this user
            $user->tokens()->where('name', 'like', '%-refresh')->delete();
        }
    }

    /**
     * Revoke all access tokens (log out from all devices).
     */
    public function logoutAll(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'default_currency' => $data['default_currency'] ?? null,
            'settings' => $data['settings'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ]));

        return $user->fresh();
    }

    /**
     * Set or update the app login PIN (6 digits).
     */
    public function setLoginPin(User $user, string $pin): void
    {
        $user->update([
            'login_pin_hash' => Hash::make($pin),
        ]);
    }

    /**
     * Set or update the transaction PIN (4 digits).
     */
    public function setTransactionPin(User $user, string $pin): void
    {
        $user->update([
            'transaction_pin_hash' => Hash::make($pin),
        ]);
    }

    /**
     * Verify the transaction PIN.
     */
    public function verifyTransactionPin(User $user, string $pin): bool
    {
        if (! $user->transaction_pin_hash) {
            return false;
        }

        return Hash::check($pin, $user->transaction_pin_hash);
    }

    /**
     * Verify a Google ID token by calling Google's tokeninfo endpoint.
     *
     * @throws ValidationException
     */
    private function verifyGoogleToken(string $idToken): array
    {
        $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $idToken,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'token' => ['Invalid Google ID token.'],
            ]);
        }

        $payload = $response->json();

        // Verify the token was issued for our app
        $expectedClientId = config('services.google.client_id');
        if ($expectedClientId && ($payload['aud'] ?? '') !== $expectedClientId) {
            throw ValidationException::withMessages([
                'token' => ['Google token was not issued for this application.'],
            ]);
        }

        return $payload;
    }

    /**
     * Request a 6-digit PIN reset verification code for a phone number.
     *
     * @throws ValidationException
     */
    public function requestPinReset(string $phoneNumber): array
    {
        $user = User::where('phone_number', $phoneNumber)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'phone_number' => ['No account found with this phone number.'],
            ]);
        }

        // Clean up previous reset requests for this phone number
        PinReset::where('phone_number', $phoneNumber)->delete();

        // Generate 6-digit numeric verification code
        $code = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        PinReset::create([
            'phone_number' => $user->phone_number,
            'email' => $user->email,
            'code' => $code,
            'expires_at' => $expiresAt,
        ]);

        // Send code via email if user has an email
        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new PinResetMail($user, $code));
            } catch (\Throwable $e) {
                // Log and continue gracefully
                report($e);
            }
        }

        // Log notification to user
        NotificationService::log(
            user: $user,
            title: 'PIN Reset Requested 🔐',
            message: 'A verification code was generated to reset your 6-digit login PIN.',
            category: 'system',
            actionRoute: '/settings',
        );

        return [
            'message' => 'Verification code sent successfully.',
            'phone_number' => $user->phone_number,
            'has_email' => !empty($user->email),
            'debug_code' => app()->environment('local', 'testing') ? $code : null,
        ];
    }

    /**
     * Verify the 6-digit PIN reset code and issue a reset token.
     *
     * @throws ValidationException
     */
    public function verifyPinResetCode(string $phoneNumber, string $code): array
    {
        $pinReset = PinReset::where('phone_number', $phoneNumber)
            ->where('code', $code)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $pinReset) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired.'],
            ]);
        }

        $resetToken = Str::random(64);
        $pinReset->update([
            'verified_at' => now(),
            'reset_token' => $resetToken,
        ]);

        return [
            'message' => 'Code verified successfully.',
            'reset_token' => $resetToken,
        ];
    }

    /**
     * Reset login PIN using verified reset token.
     *
     * @throws ValidationException
     */
    public function resetPin(
        string $phoneNumber,
        string $resetToken,
        string $newPin,
        string $deviceName = 'mobile-app'
    ): array {
        $pinReset = PinReset::where('phone_number', $phoneNumber)
            ->where('reset_token', $resetToken)
            ->whereNotNull('verified_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $pinReset) {
            throw ValidationException::withMessages([
                'reset_token' => ['The reset session is invalid or has expired. Please request a new code.'],
            ]);
        }

        $user = User::where('phone_number', $phoneNumber)->firstOrFail();

        // Update login PIN
        $user->update([
            'login_pin_hash' => Hash::make($newPin),
        ]);

        // Invalidate reset token
        $pinReset->delete();

        // Log security notification
        NotificationService::log(
            user: $user,
            title: 'Login PIN Reset Successfully 🔒',
            message: 'Your login PIN has been updated. You can now use your new PIN to sign in.',
            category: 'system',
            actionRoute: '/settings',
        );

        // Issue new authentication tokens for immediate login
        $tokens = $this->issueTokens($user, $deviceName);

        return array_merge([
            'message' => 'PIN reset successfully.',
            'user' => $user,
        ], $tokens);
    }

    /**
     * Setup TOTP Authenticator for user (secret and QR code).
     */
    public function setupTwoFactorAuthenticator(User $user): array
    {
        $secret = $user->two_factor_secret ?: $this->totpService->generateSecret();
        $user->update(['two_factor_secret' => $secret]);

        $account = $user->phone_number ?: ($user->email ?: 'User');
        $otpAuthUrl = $this->totpService->getOtpAuthUrl($secret, $account);
        $qrCodeUrl = $this->totpService->getQrCodeUrl($otpAuthUrl);

        return [
            'method' => 'authenticator',
            'secret' => $secret,
            'otpauth_url' => $otpAuthUrl,
            'qr_code_url' => $qrCodeUrl,
            'debug_code' => (app()->environment('local', 'testing')) ? $this->totpService->getCode($secret) : null,
        ];
    }

    /**
     * Send setup verification code or generate authenticator secret to configure 2FA.
     *
     * @throws ValidationException
     */
    public function sendTwoFactorSetupCode(User $user, string $method = 'authenticator', ?string $email = null, ?string $phoneNumber = null): array
    {
        if ($method === 'authenticator') {
            return $this->setupTwoFactorAuthenticator($user);
        }

        if ($method === 'email') {
            if (!empty($email) && $email !== $user->email) {
                $existing = User::where('email', $email)->where('id', '!=', $user->id)->first();
                if ($existing) {
                    throw ValidationException::withMessages([
                        'email' => ['This email address is already in use by another account.'],
                    ]);
                }
                $user->update(['email' => $email]);
            }

            if (empty($user->email)) {
                throw ValidationException::withMessages([
                    'email' => ['An email address is required to enable Email Two-Factor Authentication.'],
                ]);
            }

            $code = (string) random_int(100000, 999999);
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            try {
                Mail::to($user->email)->send(new TwoFactorMail($user, $code));
            } catch (\Throwable $e) {
                report($e);
            }

            return [
                'method' => 'email',
                'message' => 'Verification code sent to your email.',
                'email' => $user->email,
                'debug_code' => app()->environment('local', 'testing') ? $code : null,
            ];
        }

        if ($method === 'phone') {
            if (!empty($phoneNumber) && $phoneNumber !== $user->phone_number) {
                $existing = User::where('phone_number', $phoneNumber)->where('id', '!=', $user->id)->first();
                if ($existing) {
                    throw ValidationException::withMessages([
                        'phone_number' => ['This phone number is already in use by another account.'],
                    ]);
                }
                $user->update(['phone_number' => $phoneNumber]);
            }

            $code = (string) random_int(100000, 999999);
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);

            return [
                'method' => 'phone',
                'message' => 'Verification code sent to your phone number.',
                'phone_number' => $user->phone_number,
                'masked_phone' => $this->maskPhone($user->phone_number),
                'debug_code' => app()->environment('local', 'testing') ? $code : null,
            ];
        }

        throw ValidationException::withMessages([
            'method' => ['Invalid 2FA method selected.'],
        ]);
    }

    /**
     * Switch 2FA challenge method on the fly during sign-in.
     *
     * @throws ValidationException
     */
    public function switchTwoFactorChallengeMethod(string $twoFactorToken, string $method): array
    {
        $user = User::where('two_factor_token', $twoFactorToken)
            ->where('two_factor_expires_at', '>', now())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['The 2FA session is invalid or has expired. Please log in again.'],
            ]);
        }

        if (!in_array($method, ['authenticator', 'email', 'phone'], true)) {
            throw ValidationException::withMessages([
                'method' => ['Unsupported two-factor method.'],
            ]);
        }

        $code = null;
        $debugCode = null;
        $message = '';

        if ($method === 'authenticator') {
            if (empty($user->two_factor_secret)) {
                $user->update(['two_factor_secret' => $this->totpService->generateSecret()]);
                $user->refresh();
            }
            $debugCode = (app()->environment('local', 'testing') && $user->two_factor_secret)
                ? $this->totpService->getCode($user->two_factor_secret)
                : null;
            $message = 'Enter the 6-digit code from your Authenticator app.';
        } elseif ($method === 'email') {
            if (empty($user->email)) {
                throw ValidationException::withMessages([
                    'email' => ['No registered email address found on your account.'],
                ]);
            }
            $code = (string) random_int(100000, 999999);
            $debugCode = app()->environment('local', 'testing') ? $code : null;
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);
            try {
                Mail::to($user->email)->send(new TwoFactorMail($user, $code));
            } catch (\Throwable $e) {
                report($e);
            }
            $message = "A verification code has been sent to {$this->maskEmail($user->email)}.";
        } elseif ($method === 'phone') {
            $code = (string) random_int(100000, 999999);
            $debugCode = app()->environment('local', 'testing') ? $code : null;
            $user->update([
                'two_factor_code' => $code,
                'two_factor_expires_at' => now()->addMinutes(10),
            ]);
            $message = "A verification code has been sent to {$this->maskPhone($user->phone_number)}.";
        }

        return [
            'method' => $method,
            'message' => $message,
            'email' => $this->maskEmail($user->email),
            'masked_phone' => $this->maskPhone($user->phone_number),
            'debug_code' => $debugCode,
        ];
    }

    /**
     * Verify 2FA code during login and issue authentication tokens.
     *
     * @throws ValidationException
     */
    public function verifyTwoFactor(string $twoFactorToken, string $code, ?string $method = null, string $deviceName = 'mobile-app'): array
    {
        $user = User::where('two_factor_token', $twoFactorToken)
            ->where('two_factor_expires_at', '>', now())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['The 2FA session is invalid or has expired. Please log in again.'],
            ]);
        }

        $effectiveMethod = $method ?: ($user->two_factor_method ?: 'authenticator');
        $isValid = false;

        if ($effectiveMethod === 'authenticator') {
            if (!empty($user->two_factor_secret)) {
                $isValid = $this->totpService->verifyCode($user->two_factor_secret, $code);
            }
            if (!$isValid && app()->environment('local', 'testing') && ($code === '123456' || $code === $user->two_factor_code)) {
                $isValid = true;
            }
            if (!$isValid) {
                throw ValidationException::withMessages([
                    'code' => ['The authenticator verification code is incorrect or expired.'],
                ]);
            }
        } else {
            // Email or Phone OTP verification
            if ($user->two_factor_code && hash_equals((string) $user->two_factor_code, (string) $code)) {
                $isValid = true;
            } elseif (app()->environment('local', 'testing') && $code === '123456') {
                $isValid = true;
            }

            if (!$isValid) {
                throw ValidationException::withMessages([
                    'code' => ['The verification code is invalid or has expired.'],
                ]);
            }
        }

        // Clear 2FA challenge tokens
        $user->update([
            'two_factor_code' => null,
            'two_factor_token' => null,
            'two_factor_expires_at' => null,
        ]);

        NotificationService::log(
            user: $user,
            title: 'Successful 2FA Sign-In 🛡️',
            message: "You have signed in to CreditBook using two-factor authentication ({$effectiveMethod}).",
            category: 'system',
            actionRoute: '/home',
        );

        $tokens = $this->issueTokens($user, $deviceName);

        return array_merge([
            'message' => 'Two-factor authentication verified successfully.',
            'user' => $user,
        ], $tokens);
    }

    /**
     * Resend 2FA login verification code.
     *
     * @throws ValidationException
     */
    public function resendTwoFactor(string $twoFactorToken, ?string $method = null): array
    {
        $user = User::where('two_factor_token', $twoFactorToken)
            ->where('two_factor_expires_at', '>', now())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['The 2FA session is invalid or has expired. Please log in again.'],
            ]);
        }

        $effectiveMethod = $method ?: ($user->two_factor_method ?: 'authenticator');

        if ($effectiveMethod === 'authenticator') {
            $debugCode = (app()->environment('local', 'testing') && $user->two_factor_secret)
                ? $this->totpService->getCode($user->two_factor_secret)
                : null;

            return [
                'method' => 'authenticator',
                'message' => 'Please enter the 6-digit code currently displayed in your Authenticator app.',
                'debug_code' => $debugCode,
            ];
        }

        $code = (string) random_int(100000, 999999);
        $user->update([
            'two_factor_code' => $code,
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        if ($effectiveMethod === 'email' && !empty($user->email)) {
            try {
                Mail::to($user->email)->send(new TwoFactorMail($user, $code));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return [
            'method' => $effectiveMethod,
            'message' => $effectiveMethod === 'email'
                ? 'A new verification code has been sent to your email.'
                : 'A new verification code has been sent to your phone number.',
            'debug_code' => app()->environment('local', 'testing') ? $code : null,
        ];
    }

    /**
     * Verify code and enable Two-Factor Authentication with chosen method.
     *
     * @throws ValidationException
     */
    public function enableTwoFactor(User $user, string $code, string $method = 'authenticator'): User
    {
        $isValid = false;

        if ($method === 'authenticator') {
            if (!empty($user->two_factor_secret)) {
                $isValid = $this->totpService->verifyCode($user->two_factor_secret, $code);
            }
            if (!$isValid && app()->environment('local', 'testing') && ($code === '123456' || $code === $user->two_factor_code)) {
                $isValid = true;
            }
            if (!$isValid) {
                throw ValidationException::withMessages([
                    'code' => ['The authenticator verification code is incorrect.'],
                ]);
            }
        } else {
            if ($user->two_factor_code && hash_equals((string) $user->two_factor_code, (string) $code) && now()->isBefore($user->two_factor_expires_at)) {
                $isValid = true;
            } elseif (app()->environment('local', 'testing') && $code === '123456') {
                $isValid = true;
            }

            if (!$isValid) {
                throw ValidationException::withMessages([
                    'code' => ['The verification code is invalid or has expired.'],
                ]);
            }
        }

        $user->update([
            'two_factor_enabled' => true,
            'two_factor_method' => $method,
            'two_factor_code' => null,
            'two_factor_expires_at' => null,
        ]);

        NotificationService::log(
            user: $user,
            title: 'Two-Factor Authentication Enabled 🛡️',
            message: "Two-factor authentication is now active on your account using {$method}.",
            category: 'system',
            actionRoute: '/settings',
        );

        return $user->fresh();
    }

    /**
     * Disable Two-Factor Authentication.
     *
     * @throws ValidationException
     */
    public function disableTwoFactor(User $user, ?string $pin = null): User
    {
        if ($pin !== null) {
            if (!$user->login_pin_hash || !Hash::check($pin, $user->login_pin_hash)) {
                throw ValidationException::withMessages([
                    'pin' => ['The PIN is incorrect.'],
                ]);
            }
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_code' => null,
            'two_factor_token' => null,
            'two_factor_expires_at' => null,
        ]);

        NotificationService::log(
            user: $user,
            title: 'Two-Factor Authentication Disabled',
            message: 'Two-factor authentication has been disabled for your account.',
            category: 'system',
            actionRoute: '/settings',
        );

        return $user->fresh();
    }

    /**
     * Mask email for secure display (e.g. j***n@example.com).
     */
    public function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) {
            return $email;
        }

        [$name, $domain] = explode('@', $email, 2);
        if (strlen($name) <= 2) {
            $maskedName = substr($name, 0, 1) . '*';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('*', min(4, strlen($name) - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Mask phone number for secure display (e.g. +234 ••• ••• 44).
     */
    public function maskPhone(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 4) {
            return $phone;
        }

        $last4 = substr($phone, -4);
        $prefix = substr($phone, 0, min(4, strlen($phone) - 4));
        return $prefix . ' ••• ••• ' . $last4;
    }
}

