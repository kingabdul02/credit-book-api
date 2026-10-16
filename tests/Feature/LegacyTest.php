<?php

namespace Tests\Feature;

use App\Enums\AccessRequestStatus;
use App\Enums\VerificationStatus;
use App\Models\LegacyAccessRequest;
use App\Models\LegacyContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LegacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_adding_contact_auto_enables_legacy_access_without_error(): void
    {
        $user = User::factory()->create([
            'legacy_access_enabled' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/legacy-contacts', [
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Spouse',
            'is_primary' => true,
        ]);

        $response->assertStatus(201);
        $this->assertTrue($user->fresh()->legacy_access_enabled);
        $this->assertDatabaseHas('legacy_contacts', [
            'user_id' => $user->id,
            'email' => 'jane@example.com',
            'is_primary' => true,
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['contact']['access_code']);
    }

    public function test_nominee_can_submit_access_request_using_access_code(): void
    {
        $user = User::factory()->create([
            'legacy_access_enabled' => true,
        ]);

        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Spouse',
            'is_primary' => true,
            'verification_status' => VerificationStatus::PENDING,
            'access_code' => 'ABCDEF12',
        ]);

        $response = $this->postJson('/api/v1/legacy-access-requests', [
            'access_code' => 'ABCDEF12',
            'reason' => 'Emergency access required',
        ]);

        $response->assertStatus(201);
        $this->assertEquals(VerificationStatus::VERIFIED, $contact->fresh()->verification_status);
        $this->assertDatabaseHas('legacy_access_requests', [
            'legacy_contact_id' => $contact->id,
            'status' => AccessRequestStatus::PENDING->value,
        ]);
    }

    public function test_submitting_with_recovery_code_grants_instant_approved_access(): void
    {
        $recoveryCode = 'ABCD-EFGH-IJKL';
        $user = User::factory()->create([
            'legacy_access_enabled' => true,
            'legacy_recovery_code_hash' => Hash::make($recoveryCode),
        ]);

        $response = $this->postJson('/api/v1/legacy-access-requests', [
            'recovery_code' => $recoveryCode,
            'reason' => 'Owner incapacitated',
        ]);

        $response->assertStatus(201);
        $data = $response->json();
        $this->assertEquals('APPROVED', $data['access_request']['status']);
        $this->assertTrue($data['access_request']['grants_access']);
    }

    public function test_approved_estate_report_requires_access_authorization(): void
    {
        $user = User::factory()->create([
            'legacy_access_enabled' => true,
        ]);

        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Spouse',
            'is_primary' => true,
            'verification_status' => VerificationStatus::VERIFIED,
            'access_code' => 'XYZ98765',
        ]);

        $request = LegacyAccessRequest::create([
            'user_id' => $user->id,
            'legacy_contact_id' => $contact->id,
            'reason' => 'Medical Emergency',
            'recovery_code_provided' => false,
            'status' => AccessRequestStatus::APPROVED,
            'waiting_period_days' => 30,
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        // Attempt without access code -> 403
        $unauthorized = $this->getJson("/api/v1/legacy-access-requests/{$request->id}/estate-report");
        $unauthorized->assertStatus(403);

        // Attempt with valid access code header by nominee -> 200
        $authorized = $this->withHeader('X-Access-Code', 'XYZ98765')
            ->getJson("/api/v1/legacy-access-requests/{$request->id}/estate-report");
        $authorized->assertStatus(200);
        $authorized->assertJsonStructure([
            'estate_report' => [
                'account_owner',
                'receivables',
                'payables',
                'net_position',
            ],
        ]);
    }

    public function test_approver_cannot_view_nominee_estate_report(): void
    {
        $user = User::factory()->create([
            'legacy_access_enabled' => true,
        ]);

        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Spouse',
            'is_primary' => true,
            'verification_status' => VerificationStatus::VERIFIED,
            'access_code' => 'XYZ98765',
        ]);

        $request = LegacyAccessRequest::create([
            'user_id' => $user->id,
            'legacy_contact_id' => $contact->id,
            'reason' => 'Medical Emergency',
            'recovery_code_provided' => false,
            'status' => AccessRequestStatus::APPROVED,
            'waiting_period_days' => 30,
            'requested_at' => now(),
            'approved_at' => now(),
        ]);

        // When the approver (account owner) attempts to access the report -> 403 Forbidden
        Sanctum::actingAs($user);
        $approverAttempt = $this->withHeader('X-Access-Code', 'XYZ98765')
            ->getJson("/api/v1/legacy-access-requests/{$request->id}/estate-report");
        $approverAttempt->assertStatus(403);
        $approverAttempt->assertJson([
            'message' => 'Unauthorized. The estate report is exclusively accessible to the requesting nominee, not the account approver.',
        ]);
    }

    public function test_verify_email_returns_json_for_api_client(): void
    {
        $user = User::factory()->create();
        $token = 'test-token-12345';
        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Sibling',
            'verification_status' => VerificationStatus::PENDING,
            'verification_token' => Hash::make($token),
            'access_code' => 'CODE1234',
        ]);

        $response = $this->getJson("/api/v1/legacy-contacts/verify-email?email=john@example.com&token={$token}");
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Email verified successfully.',
            'contact' => [
                'email' => 'john@example.com',
                'verification_status' => 'VERIFIED',
            ],
        ]);
    }

    public function test_verify_email_returns_html_view_for_browser_client(): void
    {
        $user = User::factory()->create(['name' => 'Owner Name']);
        $token = 'browser-token-12345';
        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'Jane Nominee',
            'email' => 'jane.nominee@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Spouse',
            'verification_status' => VerificationStatus::PENDING,
            'verification_token' => Hash::make($token),
            'access_code' => 'SECURE99',
        ]);

        $response = $this->withHeader('Accept', 'text/html,application/xhtml+xml')
            ->get("/api/v1/legacy-contacts/verify-email?email=jane.nominee@example.com&token={$token}");

        $response->assertStatus(200);
        $response->assertSee('Email Verified');
        $response->assertSee('SECURE99');
        $response->assertSee('Jane Nominee');
    }

    public function test_verify_email_is_idempotent_when_visited_again(): void
    {
        $user = User::factory()->create();
        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => 'Verified Nominee',
            'email' => 'verified@example.com',
            'phone' => '+2348012345678',
            'relationship' => 'Friend',
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => now(),
            'access_code' => 'ALREADY1',
        ]);

        $response = $this->withHeader('Accept', 'text/html,application/xhtml+xml')
            ->get("/api/v1/legacy-contacts/verify-email?email=verified@example.com&token=dummy-token");

        $response->assertStatus(200);
        $response->assertSee('Email Verified');
        $response->assertSee('ALREADY1');
    }
}
