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
        $period = $period ?? 'monthly';
        $startDate = $this->getStartDate($period);

        return [
            'summary' => $this->getSummary($userId, $startDate),
            'monthly_trends' => $this->getTrends($userId, $period, $startDate),
            'top_debtors' => $this->getTopDebtors($userId),
            'top_creditors' => $this->getTopCreditors($userId),
            'repayment_rate' => $this->getRepaymentRate($userId, $startDate),
            'category_breakdown' => $this->getCategoryBreakdown($userId, $startDate),
        ];
    }

    /**
     * Summary totals for the user within a period (or lifetime if startDate is null).
     */
    private function getSummary(string $userId, ?Carbon $startDate = null): array
    {
        $lentQuery = Debt::forUser($userId)->theyOweMe();
        $borrowedQuery = Debt::forUser($userId)->iOweThem();

        if ($startDate !== null) {
            $lentQuery->where('created_at', '>=', $startDate);
            $borrowedQuery->where('created_at', '>=', $startDate);
        }

        $totalLent = (float) $lentQuery->sum('original_amount');
        $totalBorrowed = (float) $borrowedQuery->sum('original_amount');

        if ($startDate !== null) {
            $totalCollected = (float) Transaction::where('transactions.user_id', $userId)
                ->where('transactions.type', LedgerType::PAYMENT)
                ->where('transactions.created_at', '>=', $startDate)
                ->join('debts', 'debts.id', '=', 'transactions.debt_id')
                ->where('debts.transaction_type', TransactionType::THEY_OWE_ME->value)
                ->sum('transactions.amount');

            if ($totalCollected == 0.0) {
                $totalCollected = (float) Debt::forUser($userId)
                    ->theyOweMe()
                    ->where('created_at', '>=', $startDate)
                    ->sum('paid_amount');
            }

            $totalRepaid = (float) Transaction::where('transactions.user_id', $userId)
                ->where('transactions.type', LedgerType::PAYMENT)
                ->where('transactions.created_at', '>=', $startDate)
                ->join('debts', 'debts.id', '=', 'transactions.debt_id')
                ->where('debts.transaction_type', TransactionType::I_OWE_THEM->value)
                ->sum('transactions.amount');

            if ($totalRepaid == 0.0) {
                $totalRepaid = (float) Debt::forUser($userId)
                    ->iOweThem()
                    ->where('created_at', '>=', $startDate)
                    ->sum('paid_amount');
            }
        } else {
            $totalCollected = (float) Debt::forUser($userId)->theyOweMe()->sum('paid_amount');
            $totalRepaid = (float) Debt::forUser($userId)->iOweThem()->sum('paid_amount');
        }

        return [
            'total_lent' => number_format($totalLent, 2, '.', ''),
            'total_borrowed' => number_format($totalBorrowed, 2, '.', ''),
            'total_collected' => number_format($totalCollected, 2, '.', ''),
            'total_repaid' => number_format($totalRepaid, 2, '.', ''),
        ];
    }

    /**
     * Lending/borrowing trends based on timeframe.
     */
    private function getTrends(string $userId, string $period, ?Carbon $startDate): array
    {
        $normalizedPeriod = strtolower($period);

        if (in_array($normalizedPeriod, ['weekly', 'week', '7days'], true)) {
            return $this->getWeeklyTrends($userId);
        }

        if (in_array($normalizedPeriod, ['monthly', 'month', '30days', '1month'], true)) {
            return $this->getMonthlyTrendsByWeek($userId);
        }

        if (in_array($normalizedPeriod, ['yearly', 'year', '1year', '12months'], true)) {
            return $this->getYearlyTrendsByMonth($userId);
        }

        return $this->getGeneralMonthlyTrends($userId, $startDate ?? now()->subMonths(6));
    }

    /**
     * Daily points for the last 7 days.
     */
    private function getWeeklyTrends(string $userId): array
    {
        $start = now()->subDays(6)->startOfDay();

        $debts = Debt::forUser($userId)
            ->where('created_at', '>=', $start)
            ->get();

        $points = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateStr = $date->toDateString();
            $dayLabel = $date->format('D'); // e.g. Mon, Tue, Wed

            $dayDebts = $debts->filter(fn ($d) => Carbon::parse($d->created_at)->toDateString() === $dateStr);
            $lent = $dayDebts->where('transaction_type', TransactionType::THEY_OWE_ME)->sum('original_amount');
            $borrowed = $dayDebts->where('transaction_type', TransactionType::I_OWE_THEM)->sum('original_amount');

            $points[] = [
                'month' => $dayLabel,
                'lent' => number_format((float) $lent, 2, '.', ''),
                'borrowed' => number_format((float) $borrowed, 2, '.', ''),
            ];
        }

        return $points;
    }

    /**
     * 4-week intervals across the past 28-30 days.
     */
    private function getMonthlyTrendsByWeek(string $userId): array
    {
        $start = now()->subDays(28)->startOfDay();

        $debts = Debt::forUser($userId)
            ->where('created_at', '>=', $start)
            ->get();

        $points = [];
        for ($w = 3; $w >= 0; $w--) {
            $weekNum = 4 - $w;
            $weekStart = now()->subDays(($w * 7) + 6)->startOfDay();
            $weekEnd = now()->subDays($w * 7)->endOfDay();

            $weekDebts = $debts->filter(function ($d) use ($weekStart, $weekEnd) {
                $createdAt = Carbon::parse($d->created_at);
                return $createdAt->between($weekStart, $weekEnd);
            });

            $lent = $weekDebts->where('transaction_type', TransactionType::THEY_OWE_ME)->sum('original_amount');
            $borrowed = $weekDebts->where('transaction_type', TransactionType::I_OWE_THEM)->sum('original_amount');

            $points[] = [
                'month' => "W{$weekNum}",
                'lent' => number_format((float) $lent, 2, '.', ''),
                'borrowed' => number_format((float) $borrowed, 2, '.', ''),
            ];
        }

        return $points;
    }

    /**
     * 12 monthly points for yearly view.
     */
    private function getYearlyTrendsByMonth(string $userId): array
    {
        $start = now()->subMonths(11)->startOfMonth();

        $debts = Debt::forUser($userId)
            ->where('created_at', '>=', $start)
            ->get();

        $points = [];
        for ($m = 11; $m >= 0; $m--) {
            $monthDate = now()->subMonths($m);
            $monthKey = $monthDate->format('Y-m');

            $monthDebts = $debts->filter(fn ($d) => Carbon::parse($d->created_at)->format('Y-m') === $monthKey);
            $lent = $monthDebts->where('transaction_type', TransactionType::THEY_OWE_ME)->sum('original_amount');
            $borrowed = $monthDebts->where('transaction_type', TransactionType::I_OWE_THEM)->sum('original_amount');

            $points[] = [
                'month' => $monthKey,
                'lent' => number_format((float) $lent, 2, '.', ''),
                'borrowed' => number_format((float) $borrowed, 2, '.', ''),
            ];
        }

        return $points;
    }

    /**
     * Fallback monthly trends for custom date range.
     */
    private function getGeneralMonthlyTrends(string $userId, Carbon $startDate): array
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
    private function getRepaymentRate(string $userId, ?Carbon $startDate = null): array
    {
        $query = Debt::forUser($userId);

        if ($startDate !== null) {
            $hasPeriodDebts = (clone $query)->where('created_at', '>=', $startDate)->exists();
            if ($hasPeriodDebts) {
                $query->where('created_at', '>=', $startDate);
            }
        }

        $totalDebts = (clone $query)->count();
        $paidDebts = (clone $query)->where('status', DebtStatus::PAID)->count();

        $rate = $totalDebts > 0 ? round(($paidDebts / $totalDebts) * 100, 1) : 0;

        return [
            'total_debts' => $totalDebts,
            'paid_debts' => $paidDebts,
            'rate_percentage' => $rate,
        ];
    }

    /**
     * Breakdown of debts by category with percentage.
     */
    private function getCategoryBreakdown(string $userId, ?Carbon $startDate = null): array
    {
        $query = Debt::forUser($userId);

        if ($startDate !== null) {
            $periodHasDebts = (clone $query)->where('created_at', '>=', $startDate)->exists();
            if ($periodHasDebts) {
                $query->where('created_at', '>=', $startDate);
            }
        }

        $debts = $query->get();

        if ($debts->isEmpty()) {
            return [];
        }

        $grouped = $debts->groupBy(function ($debt) {
            $cat = trim((string) ($debt->category ?? ''));
            return !empty($cat) ? ucfirst($cat) : 'General';
        });

        $totalAmountAll = (float) $debts->sum('original_amount');

        $breakdown = [];
        foreach ($grouped as $category => $items) {
            $catTotal = (float) $items->sum('original_amount');
            $percentage = $totalAmountAll > 0 
                ? round(($catTotal / $totalAmountAll) * 100, 1) 
                : 0.0;

            $breakdown[] = [
                'category' => $category,
                'count' => $items->count(),
                'total_amount' => number_format($catTotal, 2, '.', ''),
                'percentage' => $percentage,
            ];
        }

        usort($breakdown, fn ($a, $b) => (float) $b['total_amount'] <=> (float) $a['total_amount']);

        return $breakdown;
    }

    private function getStartDate(string $period): ?Carbon
    {
        return match (strtolower($period)) {
            'weekly', 'week', '7days' => now()->subDays(6)->startOfDay(),
            'monthly', 'month', '30days', '1month' => now()->subDays(29)->startOfDay(),
            'yearly', 'year', '1year', '12months' => now()->subYear()->startOfDay(),
            '3months' => now()->subMonths(3)->startOfDay(),
            '6months' => now()->subMonths(6)->startOfDay(),
            'all' => null,
            default => now()->subDays(29)->startOfDay(),
        };
    }
}
