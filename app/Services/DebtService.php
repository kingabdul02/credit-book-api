<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DebtStatus;
use App\Enums\LedgerType;
use App\Enums\TransactionType;
use App\Models\Contact;
use App\Models\Debt;
use App\Services\NotificationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DebtService
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {
    }

    /**
     * List debts for a user with optional filters.
     */
    public function list(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = Debt::forUser($userId)
            ->with(['contact:id,full_name,nickname,profile_picture', 'transactions']);

        if (isset($filters['transaction_type'])) {
            $query->where('transaction_type', $filters['transaction_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('note', 'LIKE', "%{$search}%")
                    ->orWhere('category', 'LIKE', "%{$search}%")
                    ->orWhereHas('contact', function ($cq) use ($search) {
                        $cq->where('full_name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDir = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Create a new debt with its initial transaction ledger entry.
     */
    public function create(string $userId, array $data): Debt
    {
        return DB::transaction(function () use ($userId, $data) {
            $debt = Debt::create([
                'user_id' => $userId,
                'contact_id' => $data['contact_id'],
                'transaction_type' => $data['transaction_type'],
                'original_amount' => $data['amount'],
                'paid_amount' => 0,
                'currency' => $data['currency'] ?? 'NGN',
                'note' => $data['note'] ?? null,
                'category' => $data['category'] ?? null,
                'status' => DebtStatus::UNPAID,
                'due_date' => $data['due_date'] ?? null,
                'reminder_enabled' => $data['reminder_enabled'] ?? false,
                'client_id' => $data['client_id'],
            ]);

            // Create the initial ledger transaction
            $this->transactionService->createEntry(
                debt: $debt,
                userId: $userId,
                type: LedgerType::INITIAL,
                amount: (float) $data['amount'],
                note: $data['note'] ?? 'Debt recorded',
                clientId: $data['transaction_client_id'] ?? $data['client_id'] . '-initial',
            );

            // Increment contact usage count
            $contact = Contact::find($data['contact_id']);
            $contact?->incrementUsage();

            $contactName = $contact?->full_name ?? 'Contact';
            $formattedAmount = number_format((float) $data['amount'], 2);
            $typeDesc = (isset($data['transaction_type']) && ($data['transaction_type'] === TransactionType::THEY_OWE_ME->value || $data['transaction_type'] === TransactionType::THEY_OWE_ME))
                ? "lent to {$contactName}"
                : "borrowed from {$contactName}";

            NotificationService::log(
                user: $userId,
                title: 'New Debt Recorded',
                message: "Recorded ₦{$formattedAmount} {$typeDesc}.",
                category: 'debt',
                actionRoute: "/ledger/{$data['contact_id']}",
                actionData: ['debt_id' => $debt->id, 'contact_id' => $data['contact_id'], 'amount' => $data['amount']]
            );

            return $debt->load(['contact', 'transactions']);
        });
    }

    /**
     * Get a single debt with full details.
     */
    public function find(string $debtId, string $userId): Debt
    {
        return Debt::forUser($userId)
            ->with(['contact', 'transactions', 'reminders'])
            ->findOrFail($debtId);
    }

    /**
     * Update debt metadata (not financial amounts).
     */
    public function update(Debt $debt, array $data): Debt
    {
        $debt->update(array_filter([
            'note' => $data['note'] ?? null,
            'category' => $data['category'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'reminder_enabled' => $data['reminder_enabled'] ?? null,
        ], fn($value) => $value !== null));

        return $debt->fresh(['contact', 'transactions']);
    }

    /**
     * Soft-delete a debt.
     */
    public function delete(Debt $debt): void
    {
        $debt->delete();
    }

    /**
     * Record a payment against a debt.
     */
    public function recordPayment(Debt $debt, string $userId, array $data): Debt
    {
        return DB::transaction(function () use ($debt, $userId, $data) {
            // Create the payment ledger entry
            $this->transactionService->createEntry(
                debt: $debt,
                userId: $userId,
                type: LedgerType::PAYMENT,
                amount: (float) $data['amount'],
                note: $data['note'] ?? 'Payment recorded',
                clientId: $data['client_id'],
            );

            // Update paid amount and status
            $newPaidAmount = bcadd((string) $debt->paid_amount, (string) $data['amount'], 2);
            $debt->paid_amount = $newPaidAmount;

            $comparison = bccomp($newPaidAmount, (string) $debt->original_amount, 2);
            if ($comparison >= 0) {
                $debt->status = DebtStatus::PAID;
            } else {
                $debt->status = DebtStatus::PARTIALLY_PAID;
            }

            $debt->save();

            $contactName = $debt->contact?->full_name ?? 'Contact';
            $formattedPayment = number_format((float) $data['amount'], 2);
            $remaining = bcsub((string) $debt->original_amount, (string) $newPaidAmount, 2);

            if ($comparison >= 0) {
                NotificationService::log(
                    user: $userId,
                    title: 'Debt Settled! 🎉',
                    message: "Debt with {$contactName} has been fully settled with payment of ₦{$formattedPayment}.",
                    category: 'payment',
                    actionRoute: $debt->contact_id ? "/ledger/{$debt->contact_id}" : '/all-debts',
                    actionData: ['debt_id' => $debt->id, 'contact_id' => $debt->contact_id]
                );
            } else {
                $formattedRemaining = number_format((float) max(0, (float) $remaining), 2);
                NotificationService::log(
                    user: $userId,
                    title: 'Payment Recorded 💳',
                    message: "Recorded payment of ₦{$formattedPayment} for {$contactName}. Remaining balance: ₦{$formattedRemaining}.",
                    category: 'payment',
                    actionRoute: $debt->contact_id ? "/ledger/{$debt->contact_id}" : '/all-debts',
                    actionData: ['debt_id' => $debt->id, 'contact_id' => $debt->contact_id]
                );
            }

            return $debt->fresh(['contact', 'transactions']);
        });
    }

    /**
     * Record additional borrowing on an existing debt.
     */
    public function recordAdditional(Debt $debt, string $userId, array $data): Debt
    {
        return DB::transaction(function () use ($debt, $userId, $data) {
            $this->transactionService->createEntry(
                debt: $debt,
                userId: $userId,
                type: LedgerType::ADDITIONAL,
                amount: (float) $data['amount'],
                note: $data['note'] ?? 'Additional amount added',
                clientId: $data['client_id'],
            );

            $debt->original_amount = bcadd(
                (string) $debt->original_amount,
                (string) $data['amount'],
                2
            );

            // Recalculate status
            if (bccomp((string) $debt->paid_amount, '0', 2) === 0) {
                $debt->status = DebtStatus::UNPAID;
            } else {
                $debt->status = DebtStatus::PARTIALLY_PAID;
            }

            $debt->save();

            $contactName = $debt->contact?->full_name ?? 'Contact';
            $formattedAdditional = number_format((float) $data['amount'], 2);
            $formattedNewTotal = number_format((float) $debt->original_amount, 2);

            NotificationService::log(
                user: $userId,
                title: 'Debt Increased',
                message: "Added ₦{$formattedAdditional} to {$contactName}'s debt balance. New total: ₦{$formattedNewTotal}.",
                category: 'debt',
                actionRoute: $debt->contact_id ? "/ledger/{$debt->contact_id}" : '/all-debts',
                actionData: ['debt_id' => $debt->id, 'contact_id' => $debt->contact_id]
            );

            return $debt->fresh(['contact', 'transactions']);
        });
    }

    /**
     * Mark overdue debts (called by scheduled command).
     */
    public function markOverdueDebts(): int
    {
        return Debt::whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [DebtStatus::PAID, DebtStatus::OVERDUE])
            ->update(['status' => DebtStatus::OVERDUE]);
    }
}
