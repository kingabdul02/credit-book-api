<?php

declare(strict_types=1);

namespace App\Enums;

enum ReminderSchedule: string
{
    case SAME_DAY = 'SAME_DAY';
    case ONE_DAY_BEFORE = 'ONE_DAY_BEFORE';
    case THREE_DAYS_BEFORE = 'THREE_DAYS_BEFORE';
    case WEEKLY = 'WEEKLY';

    public function label(): string
    {
        return match ($this) {
            self::SAME_DAY => 'Same Day',
            self::ONE_DAY_BEFORE => '1 Day Before',
            self::THREE_DAYS_BEFORE => '3 Days Before',
            self::WEEKLY => 'Weekly',
        };
    }
}
