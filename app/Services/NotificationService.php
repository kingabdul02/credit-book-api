<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Get all notifications for the user with dynamic generation.
     */
    public function getForUser(User $user): array
    {
        // Generate notifications based on current user debts and legacy events
        $this->syncFromUserData($user);

        $notifications = $user->appNotifications()
            ->orderBy('created_at', 'desc')
            ->get();

        $unreadCount = $user->appNotifications()
            ->whereNull('read_at')
            ->count();

        return [
            'data' => $notifications,
            'unread_count' => $unreadCount,
        ];
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(User $user, string $id): ?Notification
    {
        $notification = $user->appNotifications()->find($id);
        if ($notification && is_null($notification->read_at)) {
            $notification->update(['read_at' => Carbon::now()]);
        }
        return $notification;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public function markAllAsRead(User $user): int
    {
        return $user->appNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Delete a single notification.
     */
    public function delete(User $user, string $id): bool
    {
        $notification = $user->appNotifications()->find($id);
        if ($notification) {
            return (bool) $notification->delete();
        }
        return false;
    }

    /**
     * Clear all notifications for the user.
     */
    public function clearAll(User $user): int
    {
        return $user->appNotifications()->delete();
    }

    /**
     * Create a notification for a user.
     */
    public function createNotification(User $user, array $data): Notification
    {
        return self::log(
            user: $user,
            title: $data['title'],
            message: $data['message'],
            category: $data['category'] ?? 'system',
            actionRoute: $data['action_route'] ?? null,
            actionData: $data['action_data'] ?? null,
        );
    }

    /**
     * Static helper to log a notification for any user.
     */
    public static function log(
        User|string $user,
        string $title,
        string $message,
        string $category = 'system',
        ?string $actionRoute = null,
        ?array $actionData = null
    ): Notification {
        $userId = $user instanceof User ? $user->id : $user;

        return Notification::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'category' => $category,
            'action_route' => $actionRoute,
            'action_data' => $actionData,
            'read_at' => null,
        ]);
    }

    /**
     * Dynamically generate alerts for user based on real database records.
     */
    public function syncFromUserData(User $user): void
    {
        // 1. Initial Welcome notification if user has no notifications
        if ($user->appNotifications()->count() === 0) {
            $this->createNotification($user, [
                'title' => 'Welcome to CreditBook! 🎉',
                'message' => 'Track your payables and receivables with biometrics, instant sync, and secure ledger management.',
                'category' => 'system',
                'action_route' => null,
            ]);

            $this->createNotification($user, [
                'title' => 'Protect Your Financial Estate 🛡️',
                'message' => 'Nominate a trusted Next of Kin so your financial records remain accessible in an emergency.',
                'category' => 'legacy',
                'action_route' => '/next-of-kin',
            ]);
        } else {
            // Clean up any existing welcome notification that previously had '/home'
            $user->appNotifications()
                ->where('title', 'like', 'Welcome to CreditBook%')
                ->where('action_route', '/home')
                ->update(['action_route' => null]);
        }

        // 2. Check Overdue Debts
        $today = Carbon::today();
        $overdueDebts = $user->debts()
            ->with('contact')
            ->where('status', '!=', 'SETTLED')
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->get();

        if ($overdueDebts->isNotEmpty()) {
            $overdueCount = $overdueDebts->count();
            // Check if we already notified today about overdue debts
            $alreadyNotified = $user->appNotifications()
                ->where('category', 'overdue')
                ->whereDate('created_at', $today)
                ->exists();

            if (!$alreadyNotified) {
                $firstContact = $overdueDebts->first()->contact?->full_name ?? 'a contact';
                $message = $overdueCount === 1
                    ? "Payment from {$firstContact} is past due. Tap to view and send a reminder."
                    : "You have {$overdueCount} overdue debts requiring attention. Review your ledger to send reminders.";

                $this->createNotification($user, [
                    'title' => "Overdue: {$overdueCount} Payment" . ($overdueCount > 1 ? 's' : ''),
                    'message' => $message,
                    'category' => 'overdue',
                    'action_route' => '/all-debts',
                    'action_data' => ['overdue_count' => $overdueCount],
                ]);
            }
        }

        // 3. Check Upcoming Due Dates (within next 3 days)
        $threeDaysAhead = Carbon::today()->addDays(3);
        $dueSoonDebts = $user->debts()
            ->with('contact')
            ->where('status', '!=', 'SETTLED')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$today, $threeDaysAhead])
            ->get();

        if ($dueSoonDebts->isNotEmpty()) {
            $dueSoonCount = $dueSoonDebts->count();
            $alreadyNotifiedDue = $user->appNotifications()
                ->where('category', 'dueSoon')
                ->whereDate('created_at', $today)
                ->exists();

            if (!$alreadyNotifiedDue) {
                $firstDue = $dueSoonDebts->first();
                $contactName = $firstDue->contact?->full_name ?? 'Contact';
                $this->createNotification($user, [
                    'title' => "Upcoming Due Date: {$contactName}",
                    'message' => "Debt of ₦" . number_of_amount($firstDue->remaining_amount ?? $firstDue->total_amount) . " with {$contactName} is due within 3 days.",
                    'category' => 'dueSoon',
                    'action_route' => $firstDue->contact_id ? "/ledger/{$firstDue->contact_id}" : '/all-debts',
                    'action_data' => ['debt_id' => $firstDue->id, 'contact_id' => $firstDue->contact_id],
                ]);
            }
        }

        // 4. Check Pending Legacy Requests
        $pendingRequests = $user->legacyAccessRequests()
            ->where('status', 'PENDING')
            ->get();

        if ($pendingRequests->isNotEmpty()) {
            $alreadyNotifiedReq = $user->appNotifications()
                ->where('category', 'legacy')
                ->whereDate('created_at', $today)
                ->exists();

            if (!$alreadyNotifiedReq) {
                $this->createNotification($user, [
                    'title' => 'Estate Access Request Received',
                    'message' => 'A designated Next of Kin has submitted a request to access your financial records. Review and respond.',
                    'category' => 'legacy',
                    'action_route' => '/next-of-kin',
                ]);
            }
        }
    }
}

// Helper formatting inside service file if needed
if (!function_exists('App\Services\number_of_amount')) {
    function number_of_amount($amount): string {
        return number_format((float)$amount, 2);
    }
}
