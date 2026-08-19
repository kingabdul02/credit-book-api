<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReminderResource;
use App\Http\Resources\TransactionResource;
use App\Models\Reminder;
use App\Services\AnalyticsService;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * Get dashboard summary cards.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $dashboard = $this->analyticsService->getDashboard($request->user()->id);

        $reminders = Reminder::forUser($request->user()->id)
            ->upcoming(7)
            ->with('debt.contact:id,full_name,nickname')
            ->get();

        $transactions = $this->transactionService->getRecent($request->user()->id, 5);

        return response()->json([
            'dashboard' => $dashboard,
            'upcoming_reminders' => ReminderResource::collection($reminders),
            'recent_transactions' => TransactionResource::collection($transactions),
        ]);
    }

    /**
     * Get detailed analytics insights.
     */
    public function insights(Request $request): JsonResponse
    {
        $data = $this->analyticsService->getInsights(
            $request->user()->id,
            $request->query('period', '6months'),
        );

        return response()->json([
            'insights' => $data,
        ]);
    }
}
