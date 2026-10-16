<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\PinResetMail;
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
}
