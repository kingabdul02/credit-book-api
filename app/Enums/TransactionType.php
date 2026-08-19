<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    case THEY_OWE_ME = 'THEY_OWE_ME';
    case I_OWE_THEM = 'I_OWE_THEM';

    public function label(): string
    {
        return match ($this) {
            self::THEY_OWE_ME => 'They Owe Me',
            self::I_OWE_THEM => 'I Owe Them',
        };
    }
}
