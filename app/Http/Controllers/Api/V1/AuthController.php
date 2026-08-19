<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    /**
     * Register a new user account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return response()->json([
            'message' => 'Account created successfully.',
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ], 201);
    }

    /**
     * Login with email and password.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        return response()->json([
            'message' => 'Login successful.',
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * Authenticate with Google ID token.
     */
    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $result = $this->authService->googleAuth(
            $request->validated('id_token'),
            $request->validated('device_name', 'mobile-app'),
        );

        return response()->json([
            'message' => 'Google authentication successful.',
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update user profile.
     */
    public function updateUser(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'default_currency' => ['sometimes', 'string', 'size:3'],
            'settings' => ['sometimes', 'array'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user = $this->authService->updateProfile(
            $request->user(),
            $request->only(['name', 'default_currency', 'settings', 'avatar_url']),
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Set or update login PIN (6 digits).
     */
    public function setLoginPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'digits:6'],
        ]);

        $this->authService->setLoginPin($request->user(), $request->input('pin'));

        return response()->json([
            'message' => 'Login PIN set successfully.',
        ]);
    }

    /**
     * Set or update transaction PIN (4 digits).
     */
    public function setTransactionPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'digits:4'],
        ]);

        $this->authService->setTransactionPin($request->user(), $request->input('pin'));

        return response()->json([
            'message' => 'Transaction PIN set successfully.',
        ]);
    }

    /**
     * Verify transaction PIN.
     */
    public function verifyTransactionPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'digits:4'],
        ]);

        $valid = $this->authService->verifyTransactionPin($request->user(), $request->input('pin'));

        return response()->json([
            'valid' => $valid,
        ]);
    }
}
