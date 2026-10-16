<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
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
}
