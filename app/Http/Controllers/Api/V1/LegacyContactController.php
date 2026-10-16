<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\StoreLegacyContactRequest;
use App\Http\Requests\Legacy\UpdateLegacyContactRequest;
use App\Http\Resources\LegacyAuditLogResource;
use App\Http\Resources\LegacyContactResource;
use App\Models\LegacyContact;
use App\Services\LegacyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegacyContactController extends Controller
{
    public function __construct(
        private readonly LegacyAccessService $legacyAccessService,
    ) {}

    // ──────────────────────────────────────────────
    // Legacy Access Settings
    // ──────────────────────────────────────────────

    /**
     * Get legacy access settings for the authenticated user.
     */
    public function settings(Request $request): JsonResponse
    {
        $settings = $this->legacyAccessService->getSettings($request->user());

        return response()->json(['settings' => $settings]);
    }

    /**
     * Enable Legacy Access and generate a recovery code.
     */
    public function enable(Request $request): JsonResponse
    {
        $result = $this->legacyAccessService->enableLegacyAccess($request->user());

        return response()->json([
            'message' => 'Legacy Access has been enabled.',
            'recovery_code' => $result['recovery_code'],
            'warning' => 'Save this recovery code. It will not be shown again.',
        ]);
    }

    /**
     * Disable Legacy Access.
     */
    public function disable(Request $request): JsonResponse
    {
        $this->legacyAccessService->disableLegacyAccess($request->user());

        return response()->json([
            'message' => 'Legacy Access has been disabled.',
        ]);
    }

    /**
     * Regenerate the Legacy Recovery Code.
     */
    public function regenerateRecoveryCode(Request $request): JsonResponse
    {
        $result = $this->legacyAccessService->regenerateRecoveryCode($request->user());

        return response()->json([
            'message' => 'Recovery code has been regenerated.',
            'recovery_code' => $result['recovery_code'],
            'warning' => 'Save this recovery code. It will not be shown again.',
        ]);
    }

    /**
     * Update the waiting period.
     */
    public function updateWaitingPeriod(Request $request): JsonResponse
    {
        $request->validate([
            'waiting_period_days' => ['required', 'integer', 'in:15,30,60,90'],
        ]);

        $this->legacyAccessService->updateWaitingPeriod(
            $request->user(),
            (int) $request->input('waiting_period_days'),
        );

        return response()->json([
            'message' => 'Waiting period updated.',
        ]);
    }

    // ──────────────────────────────────────────────
    // Legacy Contacts CRUD
    // ──────────────────────────────────────────────

    /**
     * List all legacy contacts for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $contacts = $this->legacyAccessService->listContacts(
            $request->user()->id,
            $request->only(['verification_status', 'per_page']),
        );

        return LegacyContactResource::collection($contacts);
    }

    /**
     * Add a new legacy contact.
     */
    public function store(StoreLegacyContactRequest $request): JsonResponse
    {
        $user = $request->user();

        // Ensure Legacy Access is enabled, auto-enabling if not yet active
        $recoveryCode = null;
        if (! $user->legacy_access_enabled) {
            $enableResult = $this->legacyAccessService->enableLegacyAccess($user);
            $recoveryCode = $enableResult['recovery_code'] ?? null;
            $user->refresh();
        }

        $result = $this->legacyAccessService->addContact($user, $request->validated());

        return response()->json([
            'message' => 'Legacy contact added. A verification email has been sent.',
            'contact' => new LegacyContactResource($result['contact']),
            'verification_token' => $result['verification_token'], // Temporary: until email delivery is wired up
            'recovery_code' => $recoveryCode,
        ], 201);
    }

    /**
     * View a single legacy contact.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $contact = LegacyContact::forUser($request->user()->id)
            ->withCount('accessRequests')
            ->findOrFail($id);

        return response()->json([
            'contact' => new LegacyContactResource($contact),
        ]);
    }

    /**
     * Update a legacy contact.
     */
    public function update(UpdateLegacyContactRequest $request, string $id): JsonResponse
    {
        $contact = LegacyContact::forUser($request->user()->id)->findOrFail($id);
        $updated = $this->legacyAccessService->updateContact($contact, $request->validated());

        return response()->json([
            'message' => 'Legacy contact updated.',
            'contact' => new LegacyContactResource($updated),
        ]);
    }

    /**
     * Remove a legacy contact.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $contact = LegacyContact::forUser($request->user()->id)->findOrFail($id);
        $this->legacyAccessService->removeContact($contact);

        return response()->json([
            'message' => 'Legacy contact removed.',
        ]);
    }

    /**
     * Resend verification email for a legacy contact.
     */
    public function resendVerification(Request $request, string $id): JsonResponse
    {
        $contact = LegacyContact::forUser($request->user()->id)->findOrFail($id);
        $result = $this->legacyAccessService->resendVerification($contact);

        return response()->json([
            'message' => 'Verification email has been resent.',
            'verification_token' => $result['verification_token'], // Temporary
        ]);
    }

    /**
     * Revoke a legacy contact's verified status.
     */
    public function revoke(Request $request, string $id): JsonResponse
    {
        $contact = LegacyContact::forUser($request->user()->id)->findOrFail($id);
        $updated = $this->legacyAccessService->revokeContact($contact);

        return response()->json([
            'message' => 'Legacy contact verification has been revoked.',
            'contact' => new LegacyContactResource($updated),
        ]);
    }

    // ──────────────────────────────────────────────
    // Audit Logs
    // ──────────────────────────────────────────────

    /**
     * List audit logs for the authenticated user.
     */
    public function auditLogs(Request $request): AnonymousResourceCollection
    {
        $logs = $this->legacyAccessService->listAuditLogs(
            $request->user()->id,
            $request->only(['action', 'per_page']),
        );

        return LegacyAuditLogResource::collection($logs);
    }

    // ──────────────────────────────────────────────
    // Public: Email Verification
    // ──────────────────────────────────────────────

    /**
     * Verify a legacy contact's email (public — no auth required).
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $contact = $this->legacyAccessService->verifyContact(
            $request->input('email'),
            $request->input('token'),
        );

        return response()->json([
            'message' => 'Email verified successfully.',
            'contact' => new LegacyContactResource($contact),
        ]);
    }
}
