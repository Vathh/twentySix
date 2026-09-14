<?php

namespace App\Services\Tournament;

use App\Domain\Game\PlayoffGameDomain;
use App\Enums\GameStatus;
use App\Events\TournamentPlayoffBracketUpdated;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use Illuminate\Support\Facades\DB;

/**
 * Live aktualizacja drabinki playoff na WWW (karty meczów + awanse).
 */
class TournamentPlayoffBracketLiveService
{
    public function __construct(
        private PlayoffGameRepository $playoffGameRepository,
    ) {}

    public function pushTournament(int $tournamentId): void
    {
        if ($tournamentId < 1) {
            return;
        }

        broadcast(new TournamentPlayoffBracketUpdated($tournamentId, $this->snapshot($tournamentId)));
    }

    public function pushTournamentAfterCommit(int $tournamentId): void
    {
        $run = function () use ($tournamentId) {
            $this->pushTournament($tournamentId);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }

    /**
     * @return array{tournamentId: int, games: list<array<string, mixed>>}
     */
    public function snapshot(int $tournamentId): array
    {
        $games = $this->playoffGameRepository->getAllForTournament($tournamentId)
            ->map(fn (PlayoffGameDomain $game) => $this->serializeGame($game))
            ->values()
            ->all();

        return [
            'tournamentId' => $tournamentId,
            'games' => $games,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGame(PlayoffGameDomain $game): array
    {
        $status = $game->status instanceof GameStatus
            ? $game->status->value
            : (string) $game->status;
        $hideScores = $game->isByeVsBye();
        $showScores = ! $hideScores && $game->status !== GameStatus::SCHEDULED;

        return [
            'id' => (int) $game->id,
            'player1Id' => $game->player1Id,
            'player2Id' => $game->player2Id,
            'player1Name' => $game->player1?->name,
            'player2Name' => $game->player2?->name,
            'player1Score' => $showScores ? (int) ($game->player1Score ?? 0) : null,
            'player2Score' => $showScores ? (int) ($game->player2Score ?? 0) : null,
            'winnerId' => $hideScores ? null : $game->winnerId,
            'status' => $status,
            'hideScores' => $hideScores,
        ];
    }
}
