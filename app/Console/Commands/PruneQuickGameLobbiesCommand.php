<?php

namespace App\Console\Commands;

use App\Repositories\QuickGame\QuickGameLobbyRepository;
use Illuminate\Console\Command;

class PruneQuickGameLobbiesCommand extends Command
{
    protected $signature = 'quick-game:prune-lobbies
                            {--hours=6 : Usuń waiting starsze niż N godzin (updated_at)}';

    protected $description = 'Usuwa martwe lobby quick game w statusie waiting (TTL)';

    public function handle(QuickGameLobbyRepository $lobbyRepository): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $cutoff = now()->subHours($hours);
        $deleted = $lobbyRepository->pruneWaitingOlderThan($cutoff);

        $this->info("Usunięto {$deleted} lobby waiting starszych niż {$hours}h (przed {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
