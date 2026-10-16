<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccessRequestStatus;
use App\Enums\VerificationStatus;
use App\Mail\LegacyContactVerificationMail;
use App\Models\LegacyAccessAuditLog;
use App\Models\LegacyAccessRequest;
use App\Models\LegacyContact;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class LegacyAccessService
{
    // ──────────────────────────────────────────────
    // Legacy Access Settings
    // ──────────────────────────────────────────────

    /**
     * Enable Legacy Access for a user and generate a recovery code.
     *
     * @return array{recovery_code: string} The raw recovery code (shown once)
     */
    public function enableLegacyAccess(User $user): array
    {
        $rawCode = $this->generateRecoveryCode();

        $user->update([
            'legacy_access_enabled' => true,
            'legacy_recovery_code_hash' => Hash::make($rawCode),
        ]);

        $this->logAction($user->id, null, 'LEGACY_ACCESS_ENABLED');

        return ['recovery_code' => $rawCode];
    }

    /**
     * Disable Legacy Access for a user.
     */
    public function disableLegacyAccess(User $user): void
    {
        $user->update([
            'legacy_access_enabled' => false,
        ]);

        $this->logAction($user->id, null, 'LEGACY_ACCESS_DISABLED');
    }

    /**
     * Update the waiting period for a user.
     */
    public function updateWaitingPeriod(User $user, int $days): void
    {
        $user->update([
            'legacy_waiting_period_days' => $days,
        ]);

        $this->logAction($user->id, null, 'WAITING_PERIOD_UPDATED', [
            'waiting_period_days' => $days,
        ]);
    }

    /**
     * Regenerate the Legacy Recovery Code.
     *
     * @return array{recovery_code: string} The new raw recovery code (shown once)
     */
    public function regenerateRecoveryCode(User $user): array
    {
        $rawCode = $this->generateRecoveryCode();

        $user->update([
            'legacy_recovery_code_hash' => Hash::make($rawCode),
        ]);

        $this->logAction($user->id, null, 'RECOVERY_CODE_REGENERATED');

        return ['recovery_code' => $rawCode];
    }

    /**
     * Get legacy access settings for a user.
     */
    public function getSettings(User $user): array
    {
        return [
            'legacy_access_enabled' => $user->legacy_access_enabled,
            'legacy_waiting_period_days' => $user->legacy_waiting_period_days,
            'has_recovery_code' => $user->legacy_recovery_code_hash !== null,
            'contacts_count' => $user->legacyContacts()->count(),
            'verified_contacts_count' => $user->legacyContacts()->verified()->count(),
        ];
    }

    // ──────────────────────────────────────────────
    // Legacy Contacts
    // ──────────────────────────────────────────────

    /**
     * List all legacy contacts for a user.
     */
    public function listContacts(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = LegacyContact::forUser($userId);

        if (isset($filters['verification_status'])) {
            $query->where('verification_status', $filters['verification_status']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Add a new Legacy Contact.
     *
     * @return array{contact: LegacyContact, verification_token: string}
     */
    public function addContact(User $user, array $data): array
    {
        $verificationToken = Str::random(64);
        $accessCode = LegacyContact::generateAccessCode();

        $contact = LegacyContact::create([
            'user_id' => $user->id,
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'relationship' => $data['relationship'],
            'is_primary' => $data['is_primary'] ?? false,
            'verification_status' => VerificationStatus::PENDING,
            'verification_token' => Hash::make($verificationToken),
            'access_code' => $accessCode,
        ]);

        $this->logAction($user->id, $contact->id, 'CONTACT_ADDED', [
            'contact_name' => $contact->full_name,
            'contact_email' => $contact->email,
        ]);

        // Dispatch email verification notification to the contact
        Mail::to($contact->email)->queue(new LegacyContactVerificationMail($contact, $verificationToken, $accessCode));
        return [
            'contact' => $contact,
            'verification_token' => $verificationToken,
            'access_code' => $accessCode,
        ];
    }

    /**
     * Update a Legacy Contact's details.
     */
    public function updateContact(LegacyContact $contact, array $data): LegacyContact
    {
        $contact->update(array_filter([
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'relationship' => $data['relationship'] ?? null,
            'is_primary' => $data['is_primary'] ?? null,
        ], fn ($value) => $value !== null));

        $this->logAction($contact->user_id, $contact->id, 'CONTACT_UPDATED');

        return $contact->fresh();
    }

    /**
     * Remove (soft-delete) a Legacy Contact.
     */
    public function removeContact(LegacyContact $contact): void
    {
        $this->logAction($contact->user_id, $contact->id, 'CONTACT_REMOVED', [
            'contact_name' => $contact->full_name,
        ]);

        $contact->delete();
    }

    /**
     * Verify a Legacy Contact's email using the verification token.
     */
    public function verifyContact(string $email, string $token): LegacyContact
    {
        $contacts = LegacyContact::where('email', $email)
            ->where('verification_status', VerificationStatus::PENDING)
            ->get();

        foreach ($contacts as $contact) {
            if (Hash::check($token, $contact->verification_token)) {
                $contact->update([
                    'verification_status' => VerificationStatus::VERIFIED,
                    'verification_token' => null,
                    'verified_at' => now(),
                ]);

                $this->logAction($contact->user_id, $contact->id, 'VERIFICATION_COMPLETED');

                return $contact->fresh();
            }
        }

        abort(404, 'Invalid verification token or email address.');
    }

    /**
     * Revoke a Legacy Contact's verification.
     */
    public function revokeContact(LegacyContact $contact): LegacyContact
    {
        $contact->update([
            'verification_status' => VerificationStatus::REVOKED,
        ]);

        $this->logAction($contact->user_id, $contact->id, 'CONTACT_REVOKED');

        return $contact->fresh();
    }

    /**
     * Resend verification email for a Legacy Contact.
     *
     * @return array{verification_token: string}
     */
    public function resendVerification(LegacyContact $contact): array
    {
        $verificationToken = Str::random(64);
        $accessCode = LegacyContact::generateAccessCode();

        $contact->update([
            'verification_status' => VerificationStatus::PENDING,
            'verification_token' => Hash::make($verificationToken),
            'verified_at' => null,
            'access_code' => $accessCode,
        ]);

        $this->logAction($contact->user_id, $contact->id, 'VERIFICATION_RESENT');

        // TODO: Dispatch email verification notification
        // Mail::to($contact->email)->queue(new LegacyContactVerificationMail($contact, $verificationToken, $accessCode));

        return [
            'verification_token' => $verificationToken,
            'access_code' => $accessCode,
        ];
    }

    // ──────────────────────────────────────────────
    // Access Requests
    // ──────────────────────────────────────────────

    /**
     * Submit a Legacy Access Request.
     */
    public function submitAccessRequestByCode(string $accessCode, array $data): LegacyAccessRequest
    {
        $contact = LegacyContact::findByAccessCode($accessCode);
        
        if (!$contact) {
            abort(404, 'Invalid access code.');
        }

        return $this->submitAccessRequest($contact, $data);
    }

    public function submitAccessRequestByRecoveryCode(string $recoveryCode, array $data): LegacyAccessRequest
    {
        // Find user by recovery code
        $user = User::whereNotNull('legacy_recovery_code_hash')
            ->get()
            ->first(function ($user) use ($recoveryCode) {
                return Hash::check($recoveryCode, $user->legacy_recovery_code_hash);
            });

        if (!$user) {
            abort(404, 'Invalid recovery code.');
        }

        // Ensure the account owner has Legacy Access enabled
        if (! $user->legacy_access_enabled) {
            $user->update(['legacy_access_enabled' => true]);
        }

        // Find primary or any contact, or create emergency nominee contact
        $contact = $user->legacyContacts()->where('is_primary', true)->first()
            ?? $user->legacyContacts()->first();

        if (!$contact) {
            $contact = $user->legacyContacts()->create([
                'full_name' => 'Emergency Nominee',
                'email' => $user->email,
                'phone' => $user->phone ?? '0000000000',
                'relationship' => 'Emergency Authorized',
                'is_primary' => true,
                'verification_status' => VerificationStatus::VERIFIED,
                'verified_at' => now(),
                'access_code' => LegacyContact::generateAccessCode(),
            ]);
        }

        // Mark that this request was made using recovery code
        $data['recovery_code_provided'] = true;

        return $this->submitAccessRequest($contact, $data);
    }

    public function submitAccessRequest(LegacyContact $contact, array $data): LegacyAccessRequest
    {
        $user = $contact->user;
        $waitingDays = $user->legacy_waiting_period_days ?? 30;

        // Check if a recovery code was provided and if it's valid
        $recoveryCodeProvided = !empty($data['recovery_code_provided']);
        if (! $recoveryCodeProvided && ! empty($data['recovery_code']) && $user->legacy_recovery_code_hash) {
            $recoveryCodeProvided = Hash::check($data['recovery_code'], $user->legacy_recovery_code_hash);
        }

        $status = $recoveryCodeProvided
            ? AccessRequestStatus::APPROVED
            : AccessRequestStatus::PENDING;

        $approvedAt = $status === AccessRequestStatus::APPROVED ? now() : null;

        $request = LegacyAccessRequest::create([
            'user_id' => $user->id,
            'legacy_contact_id' => $contact->id,
            'reason' => $data['reason'] ?? 'Legacy financial access',
            'recovery_code_provided' => $recoveryCodeProvided,
            'status' => $status,
            'waiting_period_days' => $waitingDays,
            'requested_at' => now(),
            'expires_at' => now()->addDays($waitingDays),
            'approved_at' => $approvedAt,
        ]);

        $this->logAction($user->id, $contact->id, $recoveryCodeProvided ? 'ACCESS_APPROVED_RECOVERY' : 'ACCESS_REQUESTED', [
            'request_id' => $request->id,
            'reason' => $request->reason,
            'recovery_code_provided' => $recoveryCodeProvided,
            'waiting_period_days' => $waitingDays,
        ]);

        return $request->load('legacyContact');
    }

    /**
     * Owner approves an access request.
     */
    public function approveRequest(LegacyAccessRequest $request): LegacyAccessRequest
    {
        $request->update([
            'status' => AccessRequestStatus::APPROVED,
            'approved_at' => now(),
        ]);

        $this->logAction($request->user_id, $request->legacy_contact_id, 'ACCESS_APPROVED', [
            'request_id' => $request->id,
        ]);

        return $request->fresh('legacyContact');
    }

    /**
     * Owner denies an access request.
     */
    public function denyRequest(LegacyAccessRequest $request): LegacyAccessRequest
    {
        $request->update([
            'status' => AccessRequestStatus::DENIED,
            'denied_at' => now(),
        ]);

        $this->logAction($request->user_id, $request->legacy_contact_id, 'ACCESS_DENIED', [
            'request_id' => $request->id,
        ]);

        return $request->fresh('legacyContact');
    }

    /**
     * Auto-approve all expired pending requests (called by scheduler).
     *
     * @return int Number of requests auto-approved
     */
    public function autoApproveExpiredRequests(): int
    {
        $expiredRequests = LegacyAccessRequest::expired()->get();

        $count = 0;
        foreach ($expiredRequests as $request) {
            DB::transaction(function () use ($request) {
                $request->update([
                    'status' => AccessRequestStatus::AUTO_APPROVED,
                    'approved_at' => now(),
                ]);

                $this->logAction($request->user_id, $request->legacy_contact_id, 'ACCESS_AUTO_APPROVED', [
                    'request_id' => $request->id,
                    'waiting_period_days' => $request->waiting_period_days,
                ]);
            });
            $count++;
        }

        return $count;
    }

    /**
     * List access requests for a user.
     */
    public function listRequests(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = LegacyAccessRequest::forUser($userId)
            ->with('legacyContact');

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Find a specific access request for a user.
     */
    public function findRequest(string $requestId, string $userId): LegacyAccessRequest
    {
        return LegacyAccessRequest::forUser($userId)
            ->with('legacyContact')
            ->findOrFail($requestId);
    }

    // ──────────────────────────────────────────────
    // Estate Report
    // ──────────────────────────────────────────────

    /**
     * Generate estate summary for an approved access request.
     */
    public function getEstateReport(LegacyAccessRequest $request): array
    {
        $user = $request->user;

        // Receivables: money owed TO the account owner
        $receivables = $user->debts()
            ->where('transaction_type', 'THEY_OWE_ME')
            ->whereNotIn('status', ['PAID'])
            ->with('contact:id,full_name')
            ->get()
            ->map(fn ($debt) => [
                'contact_name' => $debt->contact?->full_name ?? 'Unknown',
                'amount' => $debt->original_amount - $debt->paid_amount,
                'currency' => $debt->currency,
                'note' => $debt->note,
            ]);

        // Payables: money owed BY the account owner
        $payables = $user->debts()
            ->where('transaction_type', 'I_OWE_THEM')
            ->whereNotIn('status', ['PAID'])
            ->with('contact:id,full_name')
            ->get()
            ->map(fn ($debt) => [
                'contact_name' => $debt->contact?->full_name ?? 'Unknown',
                'amount' => $debt->original_amount - $debt->paid_amount,
                'currency' => $debt->currency,
                'note' => $debt->note,
            ]);

        $totalReceivables = $receivables->sum('amount');
        $totalPayables = $payables->sum('amount');

        $this->logAction($request->user_id, $request->legacy_contact_id, 'ESTATE_REPORT_VIEWED', [
            'request_id' => $request->id,
        ]);

        return [
            'account_owner' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'receivables' => [
                'items' => $receivables->toArray(),
                'total' => $totalReceivables,
            ],
            'payables' => [
                'items' => $payables->toArray(),
                'total' => $totalPayables,
            ],
            'net_position' => $totalReceivables - $totalPayables,
            'currency' => $user->default_currency,
            'generated_at' => now()->toISOString(),
        ];
    }

    // ──────────────────────────────────────────────
    // Audit Logging
    // ──────────────────────────────────────────────

    /**
     * List audit logs for a user.
     */
    public function listAuditLogs(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = LegacyAccessAuditLog::forUser($userId)
            ->with('legacyContact:id,full_name,email');

        if (isset($filters['action'])) {
            $query->forAction($filters['action']);
        }

        $query->orderBy('created_at', 'desc');

        return $query->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Write an audit log entry.
     */
    public function logAction(string $userId, ?string $contactId, string $action, array $metadata = []): void
    {
        LegacyAccessAuditLog::create([
            'user_id' => $userId,
            'legacy_contact_id' => $contactId,
            'action' => $action,
            'metadata' => ! empty($metadata) ? $metadata : null,
            'created_at' => now(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    /**
     * Generate a Legacy Recovery Code in ABX9-2KF7-PQ11 format.
     */
    private function generateRecoveryCode(): string
    {
        $segments = [];
        for ($i = 0; $i < 3; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        return implode('-', $segments);
    }
}
