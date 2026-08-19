<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DebtService;
use Illuminate\Console\Command;

class MarkOverdueDebtsCommand extends Command
{
    protected $signature = 'debts:mark-overdue';

    protected $description = 'Mark debts with past due dates as overdue';

    public function handle(DebtService $debtService): int
    {
        $count = $debtService->markOverdueDebts();

        $this->info("Marked {$count} debt(s) as overdue.");

        return self::SUCCESS;
    }
}
