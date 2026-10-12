<?php

namespace App\Console\Commands;

use App\Services\Badge\BadgeAwardService;
use Illuminate\Console\Command;

class BackfillHighVisitBadgesCommand extends Command
{
    protected $signature = 'badges:backfill-high-visits';

    protected $description = 'Dopisuje odznaki 100+, 140+ i 180 do meczów 501, które mają już commit odznak';

    public function handle(BadgeAwardService $awardService): int
    {
        $awarded = $awardService->backfillCommittedHighVisits();
        $this->info("Dopisano odznaki wysokich podejść dla {$awarded} meczów.");

        return self::SUCCESS;
    }
}
