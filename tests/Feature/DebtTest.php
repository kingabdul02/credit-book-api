<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebtTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->contact = Contact::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    public function test_user_can_create_debt(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/debts', [
                'contact_id' => $this->contact->id,
                'transaction_type' => 'THEY_OWE_ME',
                'amount' => 20000,
                'note' => 'Car repair loan',
                'client_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('debt.original_amount', '20000.00')
            ->assertJsonPath('debt.status', 'UNPAID');

        // Should also create an initial transaction
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_user_can_list_debts(): void
    {
        Debt::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_filter_debts_by_type(): void
    {
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
        ]);
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
            'transaction_type' => TransactionType::I_OWE_THEM,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/debts?transaction_type=THEY_OWE_ME');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_user_can_record_payment(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
            'original_amount' => 20000,
            'paid_amount' => 0,
            'status' => DebtStatus::UNPAID,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/debts/{$debt->id}/transactions", [
                'amount' => 5000,
                'note' => 'Partial payment',
                'client_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('debt.paid_amount', '5000.00')
            ->assertJsonPath('debt.status', 'PARTIALLY_PAID');
    }

    public function test_full_payment_marks_debt_as_paid(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
            'original_amount' => 10000,
            'paid_amount' => 0,
            'status' => DebtStatus::UNPAID,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/debts/{$debt->id}/transactions", [
                'amount' => 10000,
                'note' => 'Full payment',
                'client_id' => Str::uuid()->toString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('debt.status', 'PAID')
            ->assertJsonPath('debt.progress_percentage', 100);
    }

    public function test_user_can_view_debt_with_transactions(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/debts/{$debt->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'debt' => [
                    'id', 'transaction_type', 'original_amount',
                    'paid_amount', 'outstanding_amount', 'progress_percentage',
                    'status', 'contact', 'transactions',
                ],
            ]);
    }

    public function test_user_can_delete_debt(): void
    {
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/debts/{$debt->id}");

        $response->assertOk();
        $this->assertSoftDeleted('debts', ['id' => $debt->id]);
    }

    public function test_user_cannot_access_other_users_debts(): void
    {
        $otherUser = User::factory()->create();
        $otherContact = Contact::factory()->create(['user_id' => $otherUser->id]);
        $debt = Debt::factory()->create([
            'user_id' => $otherUser->id,
            'contact_id' => $otherContact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/debts/{$debt->id}");

        $response->assertStatus(404);
    }

    public function test_debt_creation_is_idempotent_by_client_id(): void
    {
        $clientId = Str::uuid()->toString();

        $payload = [
            'contact_id' => $this->contact->id,
            'transaction_type' => 'THEY_OWE_ME',
            'amount' => 15000,
            'note' => 'Idempotent loan test',
            'client_id' => $clientId,
        ];

        // First call
        $first = $this->actingAs($this->user)->postJson('/api/v1/debts', $payload);
        $first->assertStatus(201);
        $debtId = $first->json('debt.id');

        // Second call with same client_id (simulate offline replay/sync retry)
        $second = $this->actingAs($this->user)->postJson('/api/v1/debts', $payload);
        $second->assertStatus(200);
        $this->assertEquals($debtId, $second->json('debt.id'));

        // Verify only 1 debt exists in DB
        $this->assertDatabaseCount('debts', 1);
    }

    public function test_payment_recording_is_idempotent_and_resolves_debt_by_client_id(): void
    {
        $debtClientId = Str::uuid()->toString();
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $this->contact->id,
            'original_amount' => 10000,
            'paid_amount' => 0,
            'status' => DebtStatus::UNPAID,
            'client_id' => $debtClientId,
        ]);

        $paymentClientId = Str::uuid()->toString();
        $payload = [
            'amount' => 4000,
            'note' => 'Idempotent partial payment',
            'client_id' => $paymentClientId,
        ];

        // Route using debt's client_id instead of server id
        $first = $this->actingAs($this->user)->postJson("/api/v1/debts/{$debtClientId}/transactions", $payload);
        $first->assertStatus(201);

        // Replay with same transaction client_id
        $second = $this->actingAs($this->user)->postJson("/api/v1/debts/{$debtClientId}/transactions", $payload);
        $second->assertStatus(200);

        // Verify only 1 payment transaction exists
        $this->assertEquals(1, \App\Models\Transaction::where('client_id', $paymentClientId)->count());
    }
}

