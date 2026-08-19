<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'phone_number',
        'email',
        'password',
        'google_id',
        'avatar_url',
        'default_currency',
        'settings',
        'login_pin_hash',
        'transaction_pin_hash',
        'email_verified_at',
        'last_synced_at',
        'legacy_access_enabled',
        'legacy_recovery_code_hash',
        'legacy_waiting_period_days',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_pin_hash',
        'transaction_pin_hash',
        'legacy_recovery_code_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
            'legacy_access_enabled' => 'boolean',
            'legacy_waiting_period_days' => 'integer',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function syncQueue(): HasMany
    {
        return $this->hasMany(SyncQueue::class);
    }

    public function legacyContacts(): HasMany
    {
        return $this->hasMany(LegacyContact::class);
    }

    public function legacyAccessRequests(): HasMany
    {
        return $this->hasMany(LegacyAccessRequest::class);
    }

    public function legacyAuditLogs(): HasMany
    {
        return $this->hasMany(LegacyAccessAuditLog::class);
    }
}
