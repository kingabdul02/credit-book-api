<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_setup_authenticator_app_by_default(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'phone_number' => '+2348011112222',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/2fa/send-setup-code', [
                'method' => 'authenticator',
            ]);

        $response->assertOk()
            ->assertJsonPath('method', 'authenticator')
            ->assertJsonStructure(['method', 'secret', 'otpauth_url', 'qr_code_url']);

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
    }

    public function test_user_can_enable_two_factor_with_authenticator_code(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $code = $totp->getCode($secret);

        $user = User::factory()->create([
            'email' => 'test@example.com',
            'phone_number' => '+2348011112222',
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/2fa/enable', [
                'code' => $code,
                'method' => 'authenticator',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true)
            ->assertJsonPath('user.two_factor_method', 'authenticator');

        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertEquals('authenticator', $user->two_factor_method);
    }

    public function test_login_requires_authenticator_2fa_by_default(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();
        $code = $totp->getCode($secret);

        $user = User::factory()->create([
            'phone_number' => '+2348011112222',
            'email' => 'test@example.com',
            'login_pin_hash' => Hash::make('123456'),
            'two_factor_enabled' => true,
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => $secret,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348011112222',
            'pin' => '123456',
        ]);

        $response->assertOk()
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonPath('method', 'authenticator')
            ->assertJsonStructure([
                'requires_2fa',
                'two_factor_token',
                'method',
                'default_method',
                'available_methods',
                'phone_number',
                'email',
                'message',
            ]);

        $twoFactorToken = $response->json('two_factor_token');

        // Verify with the Authenticator TOTP code
        $verifyResponse = $this->postJson('/api/v1/auth/2fa/verify', [
            'two_factor_token' => $twoFactorToken,
            'code' => $code,
            'method' => 'authenticator',
        ]);

        $verifyResponse->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'phone_number', 'two_factor_enabled', 'two_factor_method'],
                'token',
                'access_token',
                'refresh_token',
            ]);
    }

    public function test_user_can_switch_method_to_email_and_verify(): void
    {
        Mail::fake();

        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $user = User::factory()->create([
            'phone_number' => '+2348011112222',
            'email' => 'test@example.com',
            'login_pin_hash' => Hash::make('123456'),
            'two_factor_enabled' => true,
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => $secret,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348011112222',
            'pin' => '123456',
        ]);

        $twoFactorToken = $loginResponse->json('two_factor_token');

        // Switch to email
        $switchResponse = $this->postJson('/api/v1/auth/2fa/switch-method', [
            'two_factor_token' => $twoFactorToken,
            'method' => 'email',
        ]);

        $switchResponse->assertOk()
            ->assertJsonPath('method', 'email')
            ->assertJsonStructure(['method', 'message', 'email', 'debug_code']);

        $user->refresh();
        $this->assertNotNull($user->two_factor_code);

        // Verify with the email code
        $verifyResponse = $this->postJson('/api/v1/auth/2fa/verify', [
            'two_factor_token' => $twoFactorToken,
            'code' => $user->two_factor_code,
            'method' => 'email',
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);
    }

    public function test_user_can_switch_method_to_phone_and_verify(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $user = User::factory()->create([
            'phone_number' => '+2348011112222',
            'email' => 'test@example.com',
            'login_pin_hash' => Hash::make('123456'),
            'two_factor_enabled' => true,
            'two_factor_method' => 'authenticator',
            'two_factor_secret' => $secret,
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'phone_number' => '+2348011112222',
            'pin' => '123456',
        ]);

        $twoFactorToken = $loginResponse->json('two_factor_token');

        // Switch to phone
        $switchResponse = $this->postJson('/api/v1/auth/2fa/switch-method', [
            'two_factor_token' => $twoFactorToken,
            'method' => 'phone',
        ]);

        $switchResponse->assertOk()
            ->assertJsonPath('method', 'phone')
            ->assertJsonStructure(['method', 'message', 'masked_phone', 'debug_code']);

        $user->refresh();
        $this->assertNotNull($user->two_factor_code);

        // Verify with the phone code
        $verifyResponse = $this->postJson('/api/v1/auth/2fa/verify', [
            'two_factor_token' => $twoFactorToken,
            'code' => $user->two_factor_code,
            'method' => 'phone',
        ]);

        $verifyResponse->assertOk()
            ->assertJsonPath('user.two_factor_enabled', true);
    }

    public function test_user_can_disable_two_factor(): void
    {
        $user = User::factory()->create([
            'phone_number' => '+2348011112222',
            'login_pin_hash' => Hash::make('123456'),
            'two_factor_enabled' => true,
            'two_factor_method' => 'authenticator',
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/auth/2fa/disable', [
                'pin' => '123456',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.two_factor_enabled', false);

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
    }
}
