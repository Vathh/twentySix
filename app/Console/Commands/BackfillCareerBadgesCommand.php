<?php

namespace App\Console\Commands;

use App\Services\Badge\CareerBadgeService;
use Illuminate\Console\Command;

class BackfillCareerBadgesCommand extends Command
{
    protected $signature = 'badges:backfill-career';

    protected $description = 'Przelicza odznaki kariery: podium, udział, tytuły i serię zwycięstw';

    public function handle(CareerBadgeService $careerBadgeService): int
    {
        $result = $careerBadgeService->backfill();
        $this->info("Przeliczono {$result['tournaments']} zakończonych turniejów i serie {$result['players']} zawodników.");

        return self::SUCCESS;
    }
}
