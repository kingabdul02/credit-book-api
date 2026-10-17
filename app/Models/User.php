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
        'two_factor_enabled',
        'two_factor_method',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_token',
        'two_factor_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'login_pin_hash',
        'transaction_pin_hash',
        'legacy_recovery_code_hash',
        'two_factor_secret',
        'two_factor_code',
        'two_factor_token',
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
            'two_factor_enabled' => 'boolean',
            'two_factor_method' => 'string',
            'two_factor_secret' => 'encrypted',
            'two_factor_expires_at' => 'datetime',
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

    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
