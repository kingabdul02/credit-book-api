<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_returns_correct_totals(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        // Someone owes me ₦20,000 (₦5,000 paid)
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 20000,
            'paid_amount' => 5000,
            'status' => DebtStatus::PARTIALLY_PAID,
        ]);

        // I owe someone ₦10,000
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::I_OWE_THEM,
            'original_amount' => 10000,
            'paid_amount' => 0,
            'status' => DebtStatus::UNPAID,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/dashboard');

        $response->assertOk()
            ->assertJsonPath('dashboard.total_owed_to_me', '15000.00')
            ->assertJsonPath('dashboard.total_i_owe', '10000.00')
            ->assertJsonPath('dashboard.net_balance', '5000.00')
            ->assertJsonPath('dashboard.active_debts_count', 2);
    }

    public function test_insights_returns_analytics_data(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/insights');

        $response->assertOk()
            ->assertJsonStructure([
                'insights' => [
                    'summary',
                    'monthly_trends',
                    'top_debtors',
                    'top_creditors',
                    'repayment_rate',
                    'category_breakdown',
                ],
            ]);
    }
}
