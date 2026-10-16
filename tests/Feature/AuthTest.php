<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Test User',
            'phone_number' => '+13001234567',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'phone_number', 'default_currency'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'phone_number' => '+13001234567',
        ]);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+13001234567',
            'login_pin_hash' => bcrypt('123456'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+13001234567',
            'pin' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'phone_number' => '+13001234567',
            'login_pin_hash' => bcrypt('123456'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+13001234567',
            'pin' => '111111',
        ]);

        $response->assertStatus(422);
    }

    public function test_registration_requires_valid_data(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'phone_number' => str_repeat('1', 25), // Too long
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'phone_number']);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/user');

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_authenticated_user_can_update_profile(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->putJson('/api/v1/auth/user', [
                'name' => 'Updated Name',
                'default_currency' => 'USD',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Updated Name')
            ->assertJsonPath('user.default_currency', 'USD');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();

        // Token should be revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('profile.jpg', 200, 200);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/avatar', [
                'avatar' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'avatar_url',
                'user' => ['id', 'name', 'avatar_url'],
            ]);

        $avatarUrl = $response->json('avatar_url');
        $this->assertNotNull($avatarUrl);
        $this->assertNotNull($user->fresh()->avatar_url);

        $filename = basename($avatarUrl);
        Storage::disk('public')->assertExists('avatars/' . $filename);
    }

    public function test_authenticated_user_can_delete_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'avatar_url' => 'http://localhost/storage/avatars/old_avatar.jpg',
        ]);
        Storage::disk('public')->put('avatars/old_avatar.jpg', 'fake content');

        $response = $this->actingAs($user)
            ->deleteJson('/api/v1/auth/avatar');

        $response->assertOk()
            ->assertJsonPath('user.avatar_url', null);

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/old_avatar.jpg');
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/user');

        $response->assertStatus(401);
    }

    public function test_can_get_avatar_publicly(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/sample.jpg', 'image-bytes');

        $response = $this->get('/api/v1/avatars/sample.jpg');

        $response->assertOk();
    }

    public function test_login_returns_access_and_refresh_tokens(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+2348011223344',
            'login_pin_hash' => bcrypt('123456'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348011223344',
            'pin' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotEmpty($response->json('refresh_token'));
    }

    public function test_can_refresh_access_token(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+2348099887766',
            'login_pin_hash' => bcrypt('123456'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348099887766',
            'pin' => '123456',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');

        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertOk()
            ->assertJsonStructure([
                'message',
                'user',
                'token',
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ]);

        $newAccessToken = $refreshResponse->json('access_token');
        $this->assertNotEmpty($newAccessToken);

        // Verify the new access token can be used to query authenticated endpoint
        $userResponse = $this->withHeader('Authorization', 'Bearer ' . $newAccessToken)
            ->getJson('/api/v1/auth/user');

        $userResponse->assertOk();
    }

    public function test_invalid_refresh_token_fails(): void
    {
        $response = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => 'invalid-token-string',
        ]);

        $response->assertStatus(422);
    }

    public function test_logout_revokes_tokens(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+2348077665544',
            'login_pin_hash' => bcrypt('123456'),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348077665544',
            'pin' => '123456',
        ]);

        $accessToken = $loginResponse->json('access_token');
        $refreshToken = $loginResponse->json('refresh_token');

        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $refreshToken,
            ]);

        $logoutResponse->assertOk()
            ->assertJsonPath('message', 'Logged out successfully.');

        // Verify tokens are deleted in database
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset auth guards in test environment
        $this->app['auth']->forgetGuards();

        // Token should now be rejected
        $userResponse = $this->withHeader('Authorization', 'Bearer ' . $accessToken)
            ->getJson('/api/v1/auth/user');

        $userResponse->assertStatus(401);

        // Refresh token should also be revoked
        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $refreshToken,
        ]);

        $refreshResponse->assertStatus(422);
    }
}

