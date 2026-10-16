<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\StoreLegacyAccessRequestRequest;
use App\Http\Resources\LegacyAccessRequestResource;
use App\Models\LegacyAccessRequest;
use App\Models\LegacyContact;
use App\Services\LegacyAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LegacyAccessRequestController extends Controller
{
    public function __construct(
        private readonly LegacyAccessService $legacyAccessService,
    ) {}

    // ──────────────────────────────────────────────
    // Public: Submit Access Request (no auth)
    // ──────────────────────────────────────────────

    /**
     * Submit a Legacy Access Request (public endpoint for verified contacts).
     */
    public function store(StoreLegacyAccessRequestRequest $request): JsonResponse
    {
        $accessCode = $request->input('access_code');
        $recoveryCode = $request->input('recovery_code');

        // Handle recovery code submission (instant emergency access)
        if (!empty($recoveryCode) && empty($accessCode)) {
            $validatedData = $request->validated();
            unset($validatedData['access_code']);

            try {
                $accessRequest = $this->legacyAccessService->submitAccessRequestByRecoveryCode(
                    $recoveryCode,
                    $validatedData,
                );

                return response()->json([
                    'message' => 'Emergency recovery verified. Access granted to financial records.',
                    'access_request' => new LegacyAccessRequestResource($accessRequest),
                ], 201);
            } catch (\Exception $e) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400);
            }
        }

        // Handle access code submission
        if (!empty($accessCode)) {
            $normalizedCode = strtoupper(trim($accessCode));
            $contact = LegacyContact::findByAccessCode($normalizedCode);

            if (! $contact) {
                return response()->json([
                    'message' => 'Invalid access code.',
                ], 404);
            }

            // Ensure contact is not revoked
            if ($contact->isRevoked()) {
                return response()->json([
                    'message' => 'This legacy contact has been revoked.',
                ], 403);
            }

            // Auto-verify contact upon entering valid access code
            if ($contact->isPending()) {
                $contact->update([
                    'verification_status' => \App\Enums\VerificationStatus::VERIFIED,
                    'verified_at' => now(),
                ]);
            }

            // Ensure the account owner has Legacy Access enabled
            if (! $contact->user->legacy_access_enabled) {
                $contact->user->update(['legacy_access_enabled' => true]);
            }

            // Check if an active request already exists for this contact
            $existing = LegacyAccessRequest::where('legacy_contact_id', $contact->id)
                ->whereIn('status', [
                    \App\Enums\AccessRequestStatus::PENDING,
                    \App\Enums\AccessRequestStatus::APPROVED,
                    \App\Enums\AccessRequestStatus::AUTO_APPROVED,
                ])
                ->latest()
                ->first();

            if ($existing) {
                $message = $existing->grantsAccess()
                    ? 'Access is granted. You can view the financial ledger.'
                    : 'An access request is already pending. The waiting period is in progress.';

                return response()->json([
                    'message' => $message,
                    'access_request' => new LegacyAccessRequestResource($existing),
                ], 200);
            }

            $validatedData = $request->validated();
            unset($validatedData['access_code']);
            if (empty($validatedData['reason'])) {
                $validatedData['reason'] = 'Legacy account financial inquiry';
            }

            try {
                $accessRequest = $this->legacyAccessService->submitAccessRequest(
                    $contact,
                    $validatedData,
                );

                return response()->json([
                    'message' => 'Access request submitted. The account owner has been notified.',
                    'access_request' => new LegacyAccessRequestResource($accessRequest),
                ], 201);
            } catch (\Exception $e) {
                $statusCode = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 400;
                return response()->json([
                    'message' => $e->getMessage(),
                ], $statusCode);
            }
        }

        return response()->json([
            'message' => 'Either access code or recovery code is required.',
        ], 400);
    }

    // ──────────────────────────────────────────────
    // Protected: Owner manages access requests
    // ──────────────────────────────────────────────

    /**
     * List all access requests for the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = $this->legacyAccessService->listRequests(
            $request->user()->id,
            $request->only(['status', 'per_page']),
        );

        return LegacyAccessRequestResource::collection($requests);
    }

    /**
     * View a single access request.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $accessRequest = $this->legacyAccessService->findRequest($id, $request->user()->id);

        return response()->json([
            'access_request' => new LegacyAccessRequestResource($accessRequest),
        ]);
    }

    /**
     * Approve an access request.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $accessRequest = LegacyAccessRequest::forUser($request->user()->id)->findOrFail($id);

        if ($accessRequest->status->value !== 'PENDING') {
            return response()->json([
                'message' => 'Only pending requests can be approved.',
            ], 422);
        }

        $updated = $this->legacyAccessService->approveRequest($accessRequest);

        return response()->json([
            'message' => 'Access request approved. The legacy contact now has read-only access.',
            'access_request' => new LegacyAccessRequestResource($updated),
        ]);
    }

    /**
     * Deny an access request.
     */
    public function deny(Request $request, string $id): JsonResponse
    {
        $accessRequest = LegacyAccessRequest::forUser($request->user()->id)->findOrFail($id);

        if ($accessRequest->status->value !== 'PENDING') {
            return response()->json([
                'message' => 'Only pending requests can be denied.',
            ], 422);
        }

        $updated = $this->legacyAccessService->denyRequest($accessRequest);

        return response()->json([
            'message' => 'Access request denied.',
            'access_request' => new LegacyAccessRequestResource($updated),
        ]);
    }

    /**
     * Get estate report for an approved access request.
     */
    public function estateReport(Request $request, string $id): JsonResponse
    {
        $accessRequest = LegacyAccessRequest::with(['legacyContact', 'user'])
            ->findOrFail($id);

        // Verify the request grants access
        if (! $accessRequest->grantsAccess()) {
            return response()->json([
                'message' => 'This access request has not been approved.',
            ], 403);
        }

        // Security safeguard: Ensure requester is authorized
        $user = $request->user();
        $isOwner = $user && $user->id === $accessRequest->user_id;

        $accessCode = $request->header('X-Access-Code') ?: $request->input('access_code');
        $recoveryCode = $request->header('X-Recovery-Code') ?: $request->input('recovery_code');

        $isValidAccessCode = $accessCode && $accessRequest->legacyContact && hash_equals(strtoupper(trim($accessRequest->legacyContact->access_code)), strtoupper(trim($accessCode)));
        $isValidRecoveryCode = $recoveryCode && $accessRequest->user->legacy_recovery_code_hash && \Illuminate\Support\Facades\Hash::check(trim($recoveryCode), $accessRequest->user->legacy_recovery_code_hash);
        $wasRecoveryAuthorized = $accessRequest->recovery_code_provided;

        if (! $isOwner && ! $isValidAccessCode && ! $isValidRecoveryCode && ! $wasRecoveryAuthorized) {
            return response()->json([
                'message' => 'Unauthorized. A valid access code or recovery code is required to view this estate report.',
            ], 403);
        }

        $report = $this->legacyAccessService->getEstateReport($accessRequest);

        return response()->json([
            'estate_report' => $report,
        ]);
    }
}
