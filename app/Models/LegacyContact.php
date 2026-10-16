<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LegacyContact extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'full_name',
        'email',
        'phone',
        'relationship',
        'is_primary',
        'verification_status',
        'verification_token',
        'verified_at',
        'access_code',
    ];

    protected $hidden = [
        'verification_token',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'verification_status' => VerificationStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accessRequests(): HasMany
    {
        return $this->hasMany(LegacyAccessRequest::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(LegacyAccessAuditLog::class);
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', VerificationStatus::VERIFIED);
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', VerificationStatus::PENDING);
    }

    public function scopeByAccessCode($query, string $code)
    {
        return $query->where('access_code', strtoupper($code));
    }

    // ──────────────────────────────────────────────
    // Methods
    // ──────────────────────────────────────────────

    /**
     * Check if this contact is verified.
     */
    public function isVerified(): bool
    {
        return $this->verification_status === VerificationStatus::VERIFIED;
    }

    /**
     * Check if this contact is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->verification_status === VerificationStatus::REVOKED;
    }

    /**
     * Check if this contact is pending verification.
     */
    public function isPending(): bool
    {
        return $this->verification_status === VerificationStatus::PENDING;
    }

    /**
     * Check if this contact has an approved access request.
     */
    public function hasApprovedAccess(): bool
    {
        return $this->accessRequests()
            ->whereIn('status', ['APPROVED', 'AUTO_APPROVED'])
            ->exists();
    }

    /**
     * Generate a unique access code for this contact.
     */
    public static function generateAccessCode(): string
    {
        do {
            $code = strtoupper(substr(str_replace(['0', 'O', '1', 'I'], '', Str::random(12)), 0, 8));
        } while (self::where('access_code', $code)->exists());

        return $code;
    }

    /**
     * Find a contact by access code.
     */
    public static function findByAccessCode(string $code): ?self
    {
        return self::where('access_code', strtoupper($code))->first();
    }
}
