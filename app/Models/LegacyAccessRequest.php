<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessRequestStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegacyAccessRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'legacy_contact_id',
        'reason',
        'recovery_code_provided',
        'status',
        'waiting_period_days',
        'requested_at',
        'expires_at',
        'approved_at',
        'denied_at',
    ];

    protected function casts(): array
    {
        return [
            'recovery_code_provided' => 'boolean',
            'status' => AccessRequestStatus::class,
            'waiting_period_days' => 'integer',
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'approved_at' => 'datetime',
            'denied_at' => 'datetime',
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

    public function scopePending($query)
    {
        return $query->where('status', AccessRequestStatus::PENDING);
    }

    public function scopeExpired($query)
    {
        return $query->pending()
            ->where('expires_at', '<=', now());
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [
            AccessRequestStatus::APPROVED,
            AccessRequestStatus::AUTO_APPROVED,
        ]);
    }

    // ──────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────

    /**
     * Check if this request grants access.
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * Check if the waiting period has expired.
     */
    public function isExpired(): bool
    {
        return $this->status === AccessRequestStatus::PENDING
            && $this->expires_at->isPast();
    }
}
