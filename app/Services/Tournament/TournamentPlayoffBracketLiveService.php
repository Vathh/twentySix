<?php

namespace App\Services\Tournament;

use App\Domain\Game\PlayoffGameDomain;
use App\Domain\Stats\ThreeDartAverageSet;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Events\TournamentPlayoffBracketUpdated;
use App\Repositories\PlayoffGame\PlayoffGameRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Services\Stats\CompetitionThreeDartAverageService;
use Illuminate\Support\Facades\DB;

/**
 * Live aktualizacja drabinki playoff na WWW (karty meczów + awanse).
 */
class TournamentPlayoffBracketLiveService
{
    public function __construct(
        private PlayoffGameRepository $playoffGameRepository,
        private TournamentRepository $tournamentRepository,
        private CompetitionThreeDartAverageService $threeDartAverages,
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
     * @return array{tournamentId: int, tournamentStatus: string|null, games: list<array<string, mixed>>}
     */
    public function snapshot(int $tournamentId): array
    {
        $domains = $this->playoffGameRepository->getAllForTournament($tournamentId);
        $averages = $this->threeDartAverages->forTournamentMatches(
            [],
            $domains->map(fn (PlayoffGameDomain $game) => (int) $game->id)->all(),
        );
        $games = $domains
            ->map(fn (PlayoffGameDomain $game) => $this->serializeGame($game, $averages))
            ->values()
            ->all();

        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId);
        $status = $tournament?->status;

        return [
            'tournamentId' => $tournamentId,
            'tournamentStatus' => $status instanceof TournamentStatus
                ? $status->value
                : ($status !== null ? (string) $status : null),
            'games' => $games,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGame(PlayoffGameDomain $game, ThreeDartAverageSet $averages): array
    {
        $status = $game->status->value;
        $hideScores = $game->isByeVsBye();
        $showScores = ! $hideScores && $game->status !== GameStatus::SCHEDULED;
        $player1Id = (int) ($game->player1Id ?? 0);
        $player2Id = (int) ($game->player2Id ?? 0);
        $gameId = (int) $game->id;

        return [
            'id' => $gameId,
            'player1Id' => $game->player1Id,
            'player2Id' => $game->player2Id,
            'player1Name' => $game->player1?->name,
            'player2Name' => $game->player2?->name,
            'player1Score' => $showScores ? (int) ($game->player1Score ?? 0) : null,
            'player2Score' => $showScores ? (int) ($game->player2Score ?? 0) : null,
            'player1Average' => $player1Id > 0 ? $averages->playoffMatchAverage($gameId, $player1Id) : null,
            'player2Average' => $player2Id > 0 ? $averages->playoffMatchAverage($gameId, $player2Id) : null,
            'winnerId' => $hideScores ? null : $game->winnerId,
            'status' => $status,
            'hideScores' => $hideScores,
        ];
    }
}
