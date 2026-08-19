<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Debt\StoreDebtRequest;
use App\Http\Requests\Debt\UpdateDebtRequest;
use App\Http\Resources\DebtResource;
use App\Services\DebtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function __construct(
        private readonly DebtService $debtService,
    ) {}

    /**
     * List all debts with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $debts = $this->debtService->list(
            $request->user()->id,
            $request->only([
                'transaction_type',
                'status',
                'contact_id',
                'date_from',
                'date_to',
                'search',
                'sort_by',
                'sort_dir',
                'per_page',
            ]),
        );

        // Calculate statistics from ALL user debts, not just current page
        $allDebts = \App\Models\Debt::forUser($request->user()->id)->get();

        $statistics = [
            'total_debts' => $allDebts->count(),

            // Counts
            'pending_count' => 0,
            'settled_count' => 0,

            // Outstanding balances
            'total_pending' => 0.0,
            'i_owe_pending' => 0.0,
            'they_owe_me_pending' => 0.0,

            // Money already paid (includes partial payments)
            'total_settled' => 0.0,
            'i_owe_settled' => 0.0,
            'they_owe_me_settled' => 0.0,
        ];

        foreach ($allDebts as $debt) {
            $isIOweThem = $debt->transaction_type === \App\Enums\TransactionType::I_OWE_THEM;

            $isPending = in_array(
                $debt->status,
                [
                    \App\Enums\DebtStatus::UNPAID,
                    \App\Enums\DebtStatus::PARTIALLY_PAID,
                    \App\Enums\DebtStatus::OVERDUE,
                ],
                true
            );

            $isPaid = $debt->status === \App\Enums\DebtStatus::PAID;

            // Count fully paid debts
            if ($isPaid) {
                $statistics['settled_count']++;
            }

            // Outstanding balance
            if ($isPending) {
                $outstanding = (float) $debt->outstanding_amount;

                $statistics['pending_count']++;
                $statistics['total_pending'] += $outstanding;

                if ($isIOweThem) {
                    $statistics['i_owe_pending'] += $outstanding;
                } else {
                    $statistics['they_owe_me_pending'] += $outstanding;
                }
            }

            // Total amount paid (includes partially paid debts)
            $paid = (float) $debt->paid_amount;

            if ($paid > 0) {
                $statistics['total_settled'] += $paid;

                if ($isIOweThem) {
                    $statistics['i_owe_settled'] += $paid;
                } else {
                    $statistics['they_owe_me_settled'] += $paid;
                }
            }
        }

        // Optional: round monetary values to 2 decimal places
        foreach (
            [
                'total_pending',
                'total_settled',
                'i_owe_pending',
                'they_owe_me_pending',
                'i_owe_settled',
                'they_owe_me_settled',
            ] as $key
        ) {
            $statistics[$key] = round($statistics[$key], 2);
        }

        return response()->json([
            'data' => DebtResource::collection($debts),
            'statistics' => $statistics,
            'meta' => [
                'current_page' => $debts->currentPage(),
                'last_page' => $debts->lastPage(),
                'per_page' => $debts->perPage(),
                'total' => $debts->total(),
            ],
        ]);
    }

    /**
     * Create a new debt record.
     */
    public function store(StoreDebtRequest $request): JsonResponse
    {
        $debt = $this->debtService->create(
            $request->user()->id,
            $request->validated(),
        );

        return response()->json([
            'message' => 'Debt recorded successfully.',
            'debt' => new DebtResource($debt),
        ], 201);
    }

    /**
     * Get a single debt with full transaction history.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $debt = $this->debtService->find($id, $request->user()->id);

        return response()->json([
            'debt' => new DebtResource($debt),
        ]);
    }

    /**
     * Update debt metadata.
     */
    public function update(UpdateDebtRequest $request, string $id): JsonResponse
    {
        $debt = $this->debtService->find($id, $request->user()->id);
        $updated = $this->debtService->update($debt, $request->validated());

        return response()->json([
            'message' => 'Debt updated successfully.',
            'debt' => new DebtResource($updated),
        ]);
    }

    /**
     * Soft-delete a debt.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $debt = $this->debtService->find($id, $request->user()->id);
        $this->debtService->delete($debt);

        return response()->json([
            'message' => 'Debt deleted successfully.',
        ]);
    }
}
