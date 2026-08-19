<?php

declare(strict_types=1);

namespace App\Enums;

enum DebtStatus: string
{
    case UNPAID = 'UNPAID';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Unpaid',
            self::PARTIALLY_PAID => 'Partially Paid',
            self::PAID => 'Paid',
            self::OVERDUE => 'Overdue',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::UNPAID => '#EF4444',
            self::PARTIALLY_PAID => '#F59E0B',
            self::PAID => '#10B981',
            self::OVERDUE => '#DC2626',
        };
    }
}
