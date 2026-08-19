<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderType: string
{
    case DUE_DATE = 'DUE_DATE';
    case PAYMENT = 'PAYMENT';
    case FOLLOW_UP = 'FOLLOW_UP';

    public function label(): string
    {
        return match ($this) {
            self::DUE_DATE => 'Due Date Reminder',
            self::PAYMENT => 'Payment Reminder',
            self::FOLLOW_UP => 'Follow-up Reminder',
        };
    }
}
