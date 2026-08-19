<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSED = 'PROCESSED';
    case CONFLICT = 'CONFLICT';
    case FAILED = 'FAILED';
}
