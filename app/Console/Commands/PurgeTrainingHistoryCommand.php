<?php

namespace App\Console\Commands;

use App\Services\Training\TrainingHistoryWipeService;
use Illuminate\Console\Command;

class PurgeTrainingHistoryCommand extends Command
{
    protected $signature = 'training:purge-history';

    protected $description = 'Trwale kasuje historię treningową ukrytą dłużej niż 30 dni';

    public function handle(TrainingHistoryWipeService $wipes): int
    {
        $purged = $wipes->purgeExpired();
        $this->info("Trwale usunięto {$purged} paczek historii treningowej.");

        return self::SUCCESS;
    }
}
