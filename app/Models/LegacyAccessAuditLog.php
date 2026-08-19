<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegacyAccessAuditLog extends Model
{
    use HasUuids;

    /**
     * Audit logs are write-only — no updated_at column.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'legacy_contact_id',
        'action',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legacyContact(): BelongsTo
    {
        return $this->belongsTo(LegacyContact::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForContact($query, string $contactId)
    {
        return $query->where('legacy_contact_id', $contactId);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }
}
