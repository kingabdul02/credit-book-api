<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'nickname',
        'phone_number',
        'profile_picture',
        'note',
        'is_favorite',
        'usage_count',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    // ──────────────────────────────────────────────
    // Computed Attributes
    // ──────────────────────────────────────────────

    /**
     * Total amount this contact owes the user.
     */
    public function getTotalOwedToUserAttribute(): string
    {
        return (string) $this->debts()
            ->theyOweMe()
            ->unpaid()
            ->selectRaw('COALESCE(SUM(original_amount - paid_amount), 0) as total')
            ->value('total');
    }

    /**
     * Total amount the user owes this contact.
     */
    public function getTotalUserOwesAttribute(): string
    {
        return (string) $this->debts()
            ->iOweThem()
            ->unpaid()
            ->selectRaw('COALESCE(SUM(original_amount - paid_amount), 0) as total')
            ->value('total');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeFrequentlyUsed($query, int $limit = 10)
    {
        return $query->orderByDesc('usage_count')->limit($limit);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('full_name', 'LIKE', "%{$term}%")
                ->orWhere('nickname', 'LIKE', "%{$term}%")
                ->orWhere('phone_number', 'LIKE', "%{$term}%");
        });
    }

    // ──────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
