<?php

declare(strict_types=1);

namespace App\Enums;

enum AccessRequestStatus: string
{
    case PENDING = 'PENDING';
    case APPROVED = 'APPROVED';
    case DENIED = 'DENIED';
    case AUTO_APPROVED = 'AUTO_APPROVED';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::DENIED => 'Denied',
            self::AUTO_APPROVED => 'Auto-Approved',
        };
    }

    /**
     * Whether this status grants read-only access.
     */
    public function grantsAccess(): bool
    {
        return match ($this) {
            self::APPROVED, self::AUTO_APPROVED => true,
            default => false,
        };
    }
}
