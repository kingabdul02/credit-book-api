<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReminderSchedule;
use App\Enums\ReminderType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'debt_id',
        'user_id',
        'remind_at',
        'type',
        'schedule',
        'is_sent',
        'client_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ReminderType::class,
            'schedule' => ReminderSchedule::class,
            'remind_at' => 'datetime',
            'is_sent' => 'boolean',
        ];
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

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->where('is_sent', false)
            ->where('remind_at', '>=', now())
            ->where('remind_at', '<=', now()->addDays($days))
            ->orderBy('remind_at');
    }

    public function scopePending($query)
    {
        return $query->where('is_sent', false)
            ->where('remind_at', '<=', now());
    }

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }
}
