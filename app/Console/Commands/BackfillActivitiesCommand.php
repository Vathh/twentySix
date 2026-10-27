<?php

namespace App\Console\Commands;

use App\Services\Activity\ActivityBackfillService;
use Illuminate\Console\Command;

class BackfillActivitiesCommand extends Command
{
    protected $signature = 'activities:backfill';

    protected $description = 'Uzupełnia tablicę o mistrzów zakończonych turniejów i pierwsze 180';

    public function handle(ActivityBackfillService $backfill): int
    {
        $result = $backfill->run();
        $this->info("Zapisano {$result['tournaments']} wygranych turniejów i {$result['first180']} pierwszych 180.");

        return self::SUCCESS;
    }
}
