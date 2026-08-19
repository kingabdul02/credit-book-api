<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LedgerType;
use App\Models\Debt;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Collection;

class TransactionService
{
    /**
     * Create an immutable ledger entry for a debt.
     */
    public function createEntry(
        Debt $debt,
        string $userId,
        LedgerType $type,
        float $amount,
        string $note,
        string $clientId,
    ): Transaction {
        return Transaction::create([
            'debt_id' => $debt->id,
            'user_id' => $userId,
            'type' => $type,
            'amount' => $amount,
            'note' => $note,
            'client_id' => $clientId,
        ]);
    }

    /**
     * Get all transactions for a specific debt.
     */
    public function getForDebt(string $debtId): Collection
    {
        return Transaction::where('debt_id', $debtId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get recent transactions across all debts for a user.
     */
    public function getRecent(string $userId, int $limit = 20): Collection
    {
        return Transaction::forUser($userId)
            ->with(['debt.contact:id,full_name,nickname,profile_picture'])
            ->recent($limit)
            ->get();
    }

    /**
     * Calculate the total payment amount for a debt from its ledger.
     * This is the "source of truth" — recalculates from the immutable ledger.
     */
    public function calculatePaidAmount(string $debtId): string
    {
        $total = Transaction::where('debt_id', $debtId)
            ->where('type', LedgerType::PAYMENT)
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * Calculate the total original amount (initial + additional) for a debt from its ledger.
     */
    public function calculateOriginalAmount(string $debtId): string
    {
        $total = Transaction::where('debt_id', $debtId)
            ->whereIn('type', [LedgerType::INITIAL, LedgerType::ADDITIONAL])
            ->sum('amount');

        return number_format((float) $total, 2, '.', '');
    }
}
