<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case PENDING = 'PENDING';
    case VERIFIED = 'VERIFIED';
    case REVOKED = 'REVOKED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::VERIFIED => 'Verified',
            self::REVOKED => 'Revoked',
        };
    }
}
