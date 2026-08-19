<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SyncAction;
use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncQueue extends Model
{
    use HasUuids;

    protected $table = 'sync_queue';

    protected $fillable = [
        'user_id',
        'entity_type',
        'entity_id',
        'action',
        'payload',
        'client_timestamp',
        'server_timestamp',
        'status',
        'conflict_data',
    ];

    protected function casts(): array
    {
        return [
            'action' => SyncAction::class,
            'status' => SyncStatus::class,
            'payload' => 'array',
            'conflict_data' => 'array',
            'client_timestamp' => 'datetime',
            'server_timestamp' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', SyncStatus::PENDING);
    }

    public function scopeConflicts($query)
    {
        return $query->where('status', SyncStatus::CONFLICT);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
