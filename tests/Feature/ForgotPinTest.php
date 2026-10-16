<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PinReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPinTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->user = User::factory()->create([
            'phone_number' => '+2348011223344',
            'email' => 'user@example.com',
            'login_pin_hash' => Hash::make('111111'),
        ]);
    }

    public function test_forgot_pin_returns_422_when_phone_not_found(): void
    {
        $response = $this->postJson('/api/v1/auth/pin/forgot', [
            'phone_number' => '+2349999999999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['phone_number']);
    }

    public function test_forgot_pin_generates_code_and_creates_reset_record(): void
    {
        $response = $this->postJson('/api/v1/auth/pin/forgot', [
            'phone_number' => '+2348011223344',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'phone_number', 'has_email']);

        $this->assertDatabaseHas('pin_resets', [
            'phone_number' => '+2348011223344',
            'email' => 'user@example.com',
        ]);

        // Verify security alert notification was logged
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'PIN Reset Requested 🔐',
            'category' => 'system',
        ]);
    }

    public function test_verify_code_fails_with_invalid_code(): void
    {
        PinReset::create([
            'phone_number' => '+2348011223344',
            'code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/pin/verify-code', [
            'phone_number' => '+2348011223344',
            'code' => '999999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_verify_code_succeeds_and_issues_reset_token(): void
    {
        PinReset::create([
            'phone_number' => '+2348011223344',
            'code' => '654321',
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/pin/verify-code', [
            'phone_number' => '+2348011223344',
            'code' => '654321',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'reset_token']);

        $resetToken = $response->json('reset_token');
        $this->assertNotEmpty($resetToken);

        $this->assertDatabaseHas('pin_resets', [
            'phone_number' => '+2348011223344',
            'reset_token' => $resetToken,
        ]);
    }

    public function test_reset_pin_validates_pin_confirmation(): void
    {
        $reset = PinReset::create([
            'phone_number' => '+2348011223344',
            'code' => '654321',
            'reset_token' => 'test-token-123',
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/pin/reset', [
            'phone_number' => '+2348011223344',
            'reset_token' => 'test-token-123',
            'pin' => '222222',
            'pin_confirmation' => '333333',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['pin']);
    }

    public function test_reset_pin_updates_login_pin_and_allows_login_with_new_pin(): void
    {
        PinReset::create([
            'phone_number' => '+2348011223344',
            'code' => '654321',
            'reset_token' => 'valid-reset-token-xyz',
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->postJson('/api/v1/auth/pin/reset', [
            'phone_number' => '+2348011223344',
            'reset_token' => 'valid-reset-token-xyz',
            'pin' => '888888',
            'pin_confirmation' => '888888',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'user', 'token', 'access_token']);

        // Check user PIN was updated in DB
        $freshUser = $this->user->fresh();
        $this->assertTrue(Hash::check('888888', $freshUser->login_pin_hash));
        $this->assertFalse(Hash::check('111111', $freshUser->login_pin_hash));

        // Token should be consumed
        $this->assertDatabaseMissing('pin_resets', [
            'reset_token' => 'valid-reset-token-xyz',
        ]);

        // Security notification logged
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Login PIN Reset Successfully 🔒',
            'category' => 'system',
        ]);

        // Verify login works with the new PIN
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348011223344',
            'pin' => '888888',
        ]);
        $loginResponse->assertOk();
    }
}
