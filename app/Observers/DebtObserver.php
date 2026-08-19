<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\DebtStatus;
use App\Models\Debt;

class DebtObserver
{
    /**
     * Handle the Debt "saving" event.
     * Auto-compute the status based on financial state.
     */
    public function saving(Debt $debt): void
    {
        // Skip if status was explicitly set during creation
        if (! $debt->exists && $debt->status !== null) {
            return;
        }

        // Auto-detect overdue status
        if (
            $debt->due_date !== null
            && $debt->due_date->isPast()
            && $debt->status !== DebtStatus::PAID
        ) {
            $debt->status = DebtStatus::OVERDUE;

            return;
        }

        // Auto-compute based on payment progress
        $paid = (float) $debt->paid_amount;
        $original = (float) $debt->original_amount;

        if ($paid <= 0) {
            // Don't override OVERDUE status
            if ($debt->status !== DebtStatus::OVERDUE) {
                $debt->status = DebtStatus::UNPAID;
            }
        } elseif ($paid >= $original) {
            $debt->status = DebtStatus::PAID;
        } else {
            $debt->status = DebtStatus::PARTIALLY_PAID;
        }
    }
}
