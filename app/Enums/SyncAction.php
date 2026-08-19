<?php

declare(strict_types=1);

namespace App\Enums;

enum SyncAction: string
{
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
}
