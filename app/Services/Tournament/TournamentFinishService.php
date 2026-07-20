<?php

namespace App\Services\Tournament;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentFinished;
use App\Models\Tournament\Tournament;
use App\Repositories\Tournament\TournamentRepository;
use Illuminate\Support\Facades\DB;

class TournamentFinishService
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private LoginCodeService $loginCodeService,
    ) {
    }

    /**
     * Zamyka turniej, gdy wszystkie mecze playoff są zakończone.
     * Usuwa kody tabletów + tokeny i broadcastuje event do aplikacji.
     *
     * @return bool true gdy status właśnie przeszedł na FINISHED
     */
    public function tryFinish(int $tournamentId): bool
    {
        $tournament = Tournament::query()
            ->with('playoffGames')
            ->findOrFail($tournamentId);

        if ($tournament->status === TournamentStatus::FINISHED) {
            return false;
        }

        if ($tournament->playoffGames->isEmpty()) {
            return false;
        }

        $allPlayoffFinished = $tournament->playoffGames->every(
            fn ($game) => $game->status === GameStatus::FINISHED,
        );

        if (! $allPlayoffFinished) {
            return false;
        }

        DB::transaction(function () use ($tournamentId) {
            $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::FINISHED);
            $this->loginCodeService->revokeForTournament($tournamentId);
        });

        event(new TournamentFinished($tournamentId));

        return true;
    }
}
