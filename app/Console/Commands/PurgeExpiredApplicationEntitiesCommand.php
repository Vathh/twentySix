<?php

namespace App\Console\Commands;

use App\Services\Retention\ApplicationEntityDeletionService;
use Illuminate\Console\Command;

class PurgeExpiredApplicationEntitiesCommand extends Command
{
    protected $signature = 'application-entities:purge-deleted';

    protected $description = 'Trwale kasuje byty aplikacji ukryte dłużej niż 90 dni';

    public function handle(ApplicationEntityDeletionService $deletionService): int
    {
        $purged = $deletionService->purgeExpired();
        $this->info("Trwale usunięto {$purged} bytów aplikacji.");

        return self::SUCCESS;
    }
}
