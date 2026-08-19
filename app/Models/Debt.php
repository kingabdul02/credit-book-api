<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebtStatus;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Debt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'contact_id',
        'transaction_type',
        'original_amount',
        'paid_amount',
        'currency',
        'note',
        'category',
        'status',
        'due_date',
        'reminder_enabled',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => TransactionType::class,
            'status' => DebtStatus::class,
            'original_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'reminder_enabled' => 'boolean',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->orderBy('created_at', 'desc');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    // ──────────────────────────────────────────────
    // Computed Attributes
    // ──────────────────────────────────────────────

    public function getOutstandingAmountAttribute(): string
    {
        return bcsub((string) $this->original_amount, (string) $this->paid_amount, 2);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ((float) $this->original_amount === 0.0) {
            return 100.0;
        }

        return round(((float) $this->paid_amount / (float) $this->original_amount) * 100, 1);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== DebtStatus::PAID;
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeTheyOweMe($query)
    {
        return $query->where('transaction_type', TransactionType::THEY_OWE_ME);
    }

    public function scopeIOweThem($query)
    {
        return $query->where('transaction_type', TransactionType::I_OWE_THEM);
    }

    public function scopeUnpaid($query)
    {
        return $query->whereIn('status', [DebtStatus::UNPAID, DebtStatus::PARTIALLY_PAID]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', DebtStatus::OVERDUE);
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
