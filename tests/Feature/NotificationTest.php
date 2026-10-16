<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertUnauthorized();
    }

    public function test_get_notifications_provisions_defaults_and_returns_list(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'message',
                        'category',
                        'action_route',
                        'is_read',
                        'created_at',
                    ],
                ],
                'unread_count',
            ]);

        $this->assertGreaterThanOrEqual(1, $response->json('unread_count'));
    }

    public function test_dynamic_overdue_debt_notification_generation(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 15000,
            'paid_amount' => 0,
            'status' => DebtStatus::OVERDUE,
            'due_date' => Carbon::now()->subDays(5),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/notifications');

        $response->assertOk();

        $notifications = collect($response->json('data'));
        $overdueNotification = $notifications->firstWhere('category', 'overdue');

        $this->assertNotNull($overdueNotification);
        $this->assertStringContainsString('Overdue', $overdueNotification['title']);
        $this->assertEquals('/all-debts', $overdueNotification['action_route']);
    }

    public function test_mark_single_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Important Update',
            'message' => 'Please check your balance',
            'category' => 'system',
        ]);

        $this->assertNull($notification->read_at);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertOk()
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_notifications_as_read(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Notice 1',
            'message' => 'Details 1',
            'category' => 'system',
        ]);
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Notice 2',
            'message' => 'Details 2',
            'category' => 'system',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/notifications/read-all');

        $response->assertOk()
            ->assertJsonPath('message', 'All notifications marked as read.');

        $unreadCount = $this->user->appNotifications()->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_delete_single_notification(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Delete me',
            'message' => 'I will be deleted',
            'category' => 'system',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Notification deleted successfully.');

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
        ]);
    }

    public function test_clear_all_notifications(): void
    {
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Notice A',
            'message' => 'Details A',
            'category' => 'system',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonPath('message', 'All notifications cleared successfully.');

        $this->assertEquals(0, $this->user->appNotifications()->count());
    }

    public function test_cannot_access_or_modify_other_user_notification(): void
    {
        $otherUser = User::factory()->create();
        $otherNotification = Notification::create([
            'user_id' => $otherUser->id,
            'title' => 'Private',
            'message' => 'Other user content',
            'category' => 'system',
        ]);

        // Attempt to mark as read
        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/notifications/{$otherNotification->id}/read");

        $response->assertNotFound();

        // Attempt to delete
        $delResponse = $this->actingAs($this->user)
            ->deleteJson("/api/v1/notifications/{$otherNotification->id}");

        $delResponse->assertNotFound();

        $this->assertDatabaseHas('notifications', [
            'id' => $otherNotification->id,
        ]);
    }

    public function test_debt_creation_logs_notification(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/debts', [
                'contact_id' => $contact->id,
                'transaction_type' => TransactionType::THEY_OWE_ME->value,
                'amount' => 50000,
                'client_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'New Debt Recorded',
            'category' => 'debt',
            'action_route' => "/ledger/{$contact->id}",
        ]);
    }

    public function test_payment_recording_logs_notification(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
            'transaction_type' => TransactionType::THEY_OWE_ME,
            'original_amount' => 10000,
            'paid_amount' => 0,
            'status' => DebtStatus::UNPAID,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/debts/{$debt->id}/transactions", [
                'type' => 'PAYMENT',
                'amount' => 10000,
                'client_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Debt Settled! 🎉',
            'category' => 'payment',
        ]);
    }

    public function test_reminder_creation_logs_notification(): void
    {
        $contact = Contact::factory()->create(['user_id' => $this->user->id]);
        $debt = Debt::factory()->create([
            'user_id' => $this->user->id,
            'contact_id' => $contact->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/reminders', [
                'debt_id' => $debt->id,
                'type' => 'PAYMENT',
                'schedule' => 'ONE_DAY_BEFORE',
                'remind_at' => Carbon::now()->addDays(2)->toIso8601String(),
                'client_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Payment Reminder Scheduled ⏰',
            'category' => 'dueSoon',
        ]);
    }

    public function test_contact_creation_logs_notification(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/contacts', [
                'full_name' => 'Jane Smith',
                'phone_number' => '+2348012345678',
                'client_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);

        $response->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Contact Added 👤',
            'category' => 'system',
        ]);
    }

    public function test_pin_setup_logs_notification(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson('/api/v1/auth/pin/transaction', [
                'pin' => '1234',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Security Alert: Transaction PIN Set 🔒',
            'category' => 'system',
            'action_route' => '/settings',
        ]);
    }
}
