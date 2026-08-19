<?php

declare(strict_types=1);

namespace App\Enums;

enum LedgerType: string
{
    case INITIAL = 'INITIAL';
    case PAYMENT = 'PAYMENT';
    case ADDITIONAL = 'ADDITIONAL';
    case ADJUSTMENT = 'ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::INITIAL => 'Initial Debt',
            self::PAYMENT => 'Payment',
            self::ADDITIONAL => 'Additional Borrowing',
            self::ADJUSTMENT => 'Adjustment',
        };
    }

    /**
     * Whether this type increases (+) or decreases (-) the outstanding balance.
     */
    public function sign(): int
    {
        return match ($this) {
            self::INITIAL, self::ADDITIONAL => 1,
            self::PAYMENT => -1,
            self::ADJUSTMENT => 0, // Can go either way, determined by context
        };
    }
}
