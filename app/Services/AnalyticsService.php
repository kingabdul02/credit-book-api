<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DebtStatus;
use App\Enums\LedgerType;
use App\Enums\TransactionType;
use App\Models\Debt;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * Get dashboard summary cards for a user.
     */
    public function getDashboard(string $userId): array
    {
        $totals = Debt::forUser($userId)
            ->whereIn('status', [DebtStatus::UNPAID, DebtStatus::PARTIALLY_PAID, DebtStatus::OVERDUE])
            ->selectRaw("
                COALESCE(SUM(CASE WHEN transaction_type = ? THEN (original_amount - paid_amount) ELSE 0 END), 0) as total_owed_to_me,
                COALESCE(SUM(CASE WHEN transaction_type = ? THEN (original_amount - paid_amount) ELSE 0 END), 0) as total_i_owe
            ", [TransactionType::THEY_OWE_ME->value, TransactionType::I_OWE_THEM->value])
            ->first();

        $totalOwedToMe = (float) $totals->total_owed_to_me;
        $totalIOwe = (float) $totals->total_i_owe;

        return [
            'total_owed_to_me' => number_format($totalOwedToMe, 2, '.', ''),
            'total_i_owe' => number_format($totalIOwe, 2, '.', ''),
            'net_balance' => number_format($totalOwedToMe - $totalIOwe, 2, '.', ''),
            'active_debts_count' => Debt::forUser($userId)->unpaid()->count(),
            'overdue_count' => Debt::forUser($userId)->overdue()->count(),
        ];
    }

    /**
     * Get detailed analytics insights.
     */
    public function getInsights(string $userId, ?string $period = null): array
    {
        $period = $period ?? '6months';
        $startDate = $this->getStartDate($period);

        return [
            'summary' => $this->getLifetimeSummary($userId),
            'monthly_trends' => $this->getMonthlyTrends($userId, $startDate),
            'top_debtors' => $this->getTopDebtors($userId),
            'top_creditors' => $this->getTopCreditors($userId),
            'repayment_rate' => $this->getRepaymentRate($userId),
            'category_breakdown' => $this->getCategoryBreakdown($userId),
        ];
    }

    /**
     * Lifetime totals for the user.
     */
    private function getLifetimeSummary(string $userId): array
    {
        $totalLent = Debt::forUser($userId)
            ->theyOweMe()
            ->sum('original_amount');

        $totalBorrowed = Debt::forUser($userId)
            ->iOweThem()
            ->sum('original_amount');

        $totalCollected = Debt::forUser($userId)
            ->theyOweMe()
            ->sum('paid_amount');

        $totalRepaid = Debt::forUser($userId)
            ->iOweThem()
            ->sum('paid_amount');

        return [
            'total_lent' => number_format((float) $totalLent, 2, '.', ''),
            'total_borrowed' => number_format((float) $totalBorrowed, 2, '.', ''),
            'total_collected' => number_format((float) $totalCollected, 2, '.', ''),
            'total_repaid' => number_format((float) $totalRepaid, 2, '.', ''),
        ];
    }

    /**
     * Monthly lending/borrowing trends.
     */
    private function getMonthlyTrends(string $userId, Carbon $startDate): array
    {
        $dateFormat = DB::connection()->getDriverName() === 'sqlite' 
            ? "strftime('%Y-%m', created_at)" 
            : "DATE_FORMAT(created_at, '%Y-%m')";

        return Debt::forUser($userId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw("
                {$dateFormat} as month,
                COALESCE(SUM(CASE WHEN transaction_type = ? THEN original_amount ELSE 0 END), 0) as lent,
                COALESCE(SUM(CASE WHEN transaction_type = ? THEN original_amount ELSE 0 END), 0) as borrowed
            ", [TransactionType::THEY_OWE_ME->value, TransactionType::I_OWE_THEM->value])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => $row->month,
                'lent' => number_format((float) $row->lent, 2, '.', ''),
                'borrowed' => number_format((float) $row->borrowed, 2, '.', ''),
            ])
            ->toArray();
    }

    /**
     * People who owe the user the most (outstanding).
     */
    private function getTopDebtors(string $userId, int $limit = 5): array
    {
        return Debt::forUser($userId)
            ->theyOweMe()
            ->unpaid()
            ->with('contact:id,full_name,nickname,profile_picture')
            ->selectRaw('contact_id, SUM(original_amount - paid_amount) as outstanding')
            ->groupBy('contact_id')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'contact' => $row->contact?->only(['id', 'full_name', 'nickname', 'profile_picture']),
                'outstanding' => number_format((float) $row->outstanding, 2, '.', ''),
            ])
            ->toArray();
    }

    /**
     * People the user owes the most (outstanding).
     */
    private function getTopCreditors(string $userId, int $limit = 5): array
    {
        return Debt::forUser($userId)
            ->iOweThem()
            ->unpaid()
            ->with('contact:id,full_name,nickname,profile_picture')
            ->selectRaw('contact_id, SUM(original_amount - paid_amount) as outstanding')
            ->groupBy('contact_id')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'contact' => $row->contact?->only(['id', 'full_name', 'nickname', 'profile_picture']),
                'outstanding' => number_format((float) $row->outstanding, 2, '.', ''),
            ])
            ->toArray();
    }

    /**
     * Overall repayment rate — percentage of debts fully paid.
     */
    private function getRepaymentRate(string $userId): array
    {
        $totalDebts = Debt::forUser($userId)->count();
        $paidDebts = Debt::forUser($userId)->where('status', DebtStatus::PAID)->count();

        $rate = $totalDebts > 0 ? round(($paidDebts / $totalDebts) * 100, 1) : 0;

        return [
            'total_debts' => $totalDebts,
            'paid_debts' => $paidDebts,
            'rate_percentage' => $rate,
        ];
    }

    /**
     * Breakdown of debts by category.
     */
    private function getCategoryBreakdown(string $userId): array
    {
        return Debt::forUser($userId)
            ->whereNotNull('category')
            ->selectRaw('category, COUNT(*) as count, SUM(original_amount) as total_amount')
            ->groupBy('category')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'count' => $row->count,
                'total_amount' => number_format((float) $row->total_amount, 2, '.', ''),
            ])
            ->toArray();
    }

    private function getStartDate(string $period): Carbon
    {
        return match ($period) {
            '1month' => now()->subMonth(),
            '3months' => now()->subMonths(3),
            '6months' => now()->subMonths(6),
            '1year' => now()->subYear(),
            'all' => now()->subYears(10),
            default => now()->subMonths(6),
        };
    }
}
