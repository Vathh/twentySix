<?php

namespace App\Console\Commands;

use App\Services\Activity\PlayerAchievementPublisher;
use Illuminate\Console\Command;

class BackfillPlayerAchievementsCommand extends Command
{
    protected $signature = 'activities:backfill-achievements';

    protected $description = 'Uzupełnia tablicę o odznaki i życiówki z rozegranych meczów';

    public function handle(PlayerAchievementPublisher $publisher): int
    {
        $result = $publisher->backfill();
        $this->info("Sprawdzono {$result['matches']} meczów i {$result['tournaments']} turniejów.");

        return self::SUCCESS;
    }
}
