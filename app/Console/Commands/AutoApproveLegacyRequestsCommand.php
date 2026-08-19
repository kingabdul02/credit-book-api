<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LegacyAccessService;
use Illuminate\Console\Command;

class AutoApproveLegacyRequestsCommand extends Command
{
    protected $signature = 'legacy:auto-approve';

    protected $description = 'Auto-approve legacy access requests that have exceeded the waiting period';

    public function handle(LegacyAccessService $legacyAccessService): int
    {
        $count = $legacyAccessService->autoApproveExpiredRequests();

        if ($count > 0) {
            $this->info("Auto-approved {$count} legacy access request(s).");
        } else {
            $this->info('No expired pending requests found.');
        }

        return self::SUCCESS;
    }
}
