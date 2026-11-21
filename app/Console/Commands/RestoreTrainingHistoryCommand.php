<?php

namespace App\Console\Commands;

use App\Services\Training\TrainingHistoryWipeService;
use Illuminate\Console\Command;

class RestoreTrainingHistoryCommand extends Command
{
    protected $signature = 'training:restore-history {userId : ID użytkownika}';

    protected $description = 'Przywraca ostatnią ukrytą historię treningową użytkownika (programista, do 30 dni)';

    public function handle(TrainingHistoryWipeService $wipes): int
    {
        $userId = (int) $this->argument('userId');
        if ($userId < 1) {
            $this->error('Nieprawidłowe userId.');

            return self::FAILURE;
        }

        if (! $wipes->restoreLatestForUser($userId)) {
            $this->warn("Brak otwartego wipe dla użytkownika {$userId}.");

            return self::FAILURE;
        }

        $this->info("Przywrócono historię treningową użytkownika {$userId}.");

        return self::SUCCESS;
    }
}
