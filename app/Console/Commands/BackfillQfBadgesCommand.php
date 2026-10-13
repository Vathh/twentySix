<?php

namespace App\Console\Commands;

use App\Services\Badge\BadgeAwardService;
use Illuminate\Console\Command;

class BackfillQfBadgesCommand extends Command
{
    protected $signature = 'badges:backfill-qf';

    protected $description = 'Dopisuje odznaki QF 9–19 do meczów 501, które mają już commit odznak';

    public function handle(BadgeAwardService $awardService): int
    {
        $awarded = $awardService->backfillCommittedQf();
        $this->info("Dopisano odznaki QF dla {$awarded} meczów.");

        return self::SUCCESS;
    }
}
