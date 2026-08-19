<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable ledger entry — transactions are NEVER updated or deleted.
 * This ensures a complete, auditable financial history.
 */
class Transaction extends Model
{
    use HasFactory, HasUuids;

    const UPDATED_AT = null; // Immutable — no updated_at column

    protected $fillable = [
        'debt_id',
        'user_id',
        'type',
        'amount',
        'note',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => LedgerType::class,
            'amount' => 'decimal:2',
        ];
    }

    // ──────────────────────────────────────────────
    // Immutability Guards
    // ──────────────────────────────────────────────

    /**
     * Prevent updates to transaction records.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new \LogicException('Transaction records are immutable and cannot be updated.');
        }

        return parent::save($options);
    }

    /**
     * Prevent deletion of transaction records.
     */
    public function delete(): ?bool
    {
        throw new \LogicException('Transaction records are immutable and cannot be deleted.');
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function debt(): BelongsTo
    {
        return $this->belongsTo(Debt::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePayments($query)
    {
        return $query->where('type', LedgerType::PAYMENT);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }
}
