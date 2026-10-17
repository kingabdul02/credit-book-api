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
                    'summary' => ['total_lent', 'total_borrowed', 'total_collected', 'total_repaid'],
                    'monthly_trends',
                    'top_debtors',
                    'top_creditors',
                    'repayment_rate',
                    'category_breakdown',
                ],
            ]);
    }

    public function test_insights_timeframes_filter_summary_and_trends(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        // Debt created today (within weekly, monthly, yearly)
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 5000,
            'paid_amount' => 0,
            'category' => 'Business',
            'created_at' => now(),
        ]);

        // Debt created 20 days ago (within monthly, yearly, but outside weekly)
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 10000,
            'paid_amount' => 0,
            'category' => 'Personal',
            'created_at' => now()->subDays(20),
        ]);

        // Debt created 60 days ago (within yearly, outside weekly and monthly)
        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 20000,
            'paid_amount' => 0,
            'category' => null, // Test null category fallback
            'created_at' => now()->subDays(60),
        ]);

        // 1. Weekly Insights
        $weeklyResponse = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/insights?period=weekly');
        $weeklyResponse->assertOk();
        $weeklyData = $weeklyResponse->json('insights');
        $this->assertEquals('5000.00', $weeklyData['summary']['total_lent']);
        $this->assertCount(7, $weeklyData['monthly_trends']); // 7 daily points

        // 2. Monthly Insights
        $monthlyResponse = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/insights?period=monthly');
        $monthlyResponse->assertOk();
        $monthlyData = $monthlyResponse->json('insights');
        $this->assertEquals('15000.00', $monthlyData['summary']['total_lent']);
        $this->assertCount(4, $monthlyData['monthly_trends']); // 4 weekly points

        // 3. Yearly Insights
        $yearlyResponse = $this->actingAs($this->user)
            ->getJson('/api/v1/analytics/insights?period=yearly');
        $yearlyResponse->assertOk();
        $yearlyData = $yearlyResponse->json('insights');
        $this->assertEquals('35000.00', $yearlyData['summary']['total_lent']);
        $this->assertCount(12, $yearlyData['monthly_trends']); // 12 monthly points

        // Category breakdown in yearly should include General (from null category) and have percentage
        $categories = collect($yearlyData['category_breakdown']);
        $this->assertTrue($categories->contains('category', 'General'));
        $this->assertTrue($categories->contains('category', 'Business'));
        $this->assertTrue($categories->contains('category', 'Personal'));
        foreach ($yearlyData['category_breakdown'] as $catItem) {
            $this->assertArrayHasKey('percentage', $catItem);
            $this->assertGreaterThan(0, $catItem['percentage']);
        }
    }
}
