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
use Illuminate\Support\Facades\Storage;

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
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'default_currency' => ['sometimes', 'string', 'size:3'],
            'settings' => ['sometimes', 'array'],
            'avatar_url' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user = $this->authService->updateProfile(
            $request->user(),
            $request->only(['name', 'email', 'default_currency', 'settings', 'avatar_url']),
        );

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Serve a user avatar publicly.
     * Bypasses web server symlink 403 restrictions by streaming directly via PHP.
     */
    public function getAvatar(string $filename)
    {
        $clean = basename($filename);
        $path = 'avatars/' . $clean;

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path);
        }

        abort(404, 'Avatar not found');
    }

    /**
     * Upload or replace the user's avatar image.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $user = $request->user();

        // Delete old local avatar if one exists
        if ($user->avatar_url) {
            $oldFilename = basename($user->avatar_url);
            Storage::disk('public')->delete('avatars/' . $oldFilename);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $filename = basename($path);

        // Generate full URL matching incoming request scheme & host,
        // using the direct /api/v1/avatars endpoint to bypass LiteSpeed/Nginx symlink 403 restrictions.
        $scheme = $request->isSecure() ? 'https' : ($request->header('X-Forwarded-Proto') ?? $request->getScheme());
        $host = $request->getHttpHost();
        $avatarUrl = "{$scheme}://{$host}/api/v1/avatars/{$filename}";

        $user->update(['avatar_url' => $avatarUrl]);

        return response()->json([
            'message' => 'Profile photo updated successfully.',
            'avatar_url' => $avatarUrl,
            'user' => new UserResource($user->fresh()),
        ]);
    }

    /**
     * Delete the user's avatar image.
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_url) {
            $oldFilename = basename($user->avatar_url);
            Storage::disk('public')->delete('avatars/' . $oldFilename);
        }

        $user->update(['avatar_url' => null]);

        return response()->json([
            'message' => 'Profile photo removed successfully.',
            'user' => new UserResource($user->fresh()),
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
