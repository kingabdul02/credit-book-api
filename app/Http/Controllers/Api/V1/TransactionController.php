<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\LedgerType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\DebtService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly DebtService $debtService,
    ) {}

    /**
     * List transactions for a specific debt.
     */
    public function index(Request $request, string $debtId): AnonymousResourceCollection
    {
        // Ensure the debt belongs to the user
        $this->debtService->find($debtId, $request->user()->id);

        $transactions = $this->transactionService->getForDebt($debtId);

        return TransactionResource::collection($transactions);
    }

    /**
     * Record a payment or additional amount for a debt.
     */
    public function store(StoreTransactionRequest $request, string $debtId): JsonResponse
    {
        $debt = $this->debtService->find($debtId, $request->user()->id);
        $type = $request->validated('type', 'PAYMENT');

        if ($type === 'ADDITIONAL') {
            $updatedDebt = $this->debtService->recordAdditional(
                $debt,
                $request->user()->id,
                $request->validated(),
            );
        } else {
            $updatedDebt = $this->debtService->recordPayment(
                $debt,
                $request->user()->id,
                $request->validated(),
            );
        }

        return response()->json([
            'message' => $type === 'ADDITIONAL'
                ? 'Additional amount recorded.'
                : 'Payment recorded successfully.',
            'debt' => new \App\Http\Resources\DebtResource($updatedDebt),
        ], 201);
    }

    /**
     * Get recent transactions across all debts.
     */
    public function recent(Request $request): AnonymousResourceCollection
    {
        $limit = (int) $request->query('limit', 20);
        $transactions = $this->transactionService->getRecent(
            $request->user()->id,
            min($limit, 50),
        );

        return TransactionResource::collection($transactions);
    }
}
