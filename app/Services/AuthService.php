<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AuthService
{
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

        $token = $user->createToken(
            $data['device_name'] ?? 'mobile-app'
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
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

        $token = $user->createToken(
            $data['device_name'] ?? 'mobile-app'
        )->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
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

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user,
            'token' => $token,
        ];
    }

    /**
     * Revoke the current access token.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();
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
