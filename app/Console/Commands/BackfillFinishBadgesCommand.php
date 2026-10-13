<?php

namespace App\Console\Commands;

use App\Services\Badge\BadgeAwardService;
use Illuminate\Console\Command;

class BackfillFinishBadgesCommand extends Command
{
    protected $signature = 'badges:backfill-finish';

    protected $description = 'Dopisuje odznakę finish 100+ do meczów 501, które mają już commit odznak';

    public function handle(BadgeAwardService $awardService): int
    {
        $awarded = $awardService->backfillCommittedFinishes();
        $this->info("Dopisano odznakę finish 100+ dla {$awarded} meczów.");

        return self::SUCCESS;
    }
}
