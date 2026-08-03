<?php

namespace App\Services\Tournament;

use App\Enums\GameStatus;
use App\Events\TournamentGroupMatrixUpdated;
use App\Models\Game\Game;
use App\Models\GroupStanding\GroupStanding;
use App\Repositories\Game\GameRepository;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\ViewModels\TournamentDataViewModel;
use Illuminate\Support\Facades\DB;

/**
 * Live aktualizacja macierzy grup na WWW (komórki wyników + standings po finished).
 */
class TournamentGroupMatrixLiveService
{
    public function __construct(
        private GameRepository $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
        private TournamentRepository $tournamentRepository,
    ) {
    }

    /**
     * Po zmianie wyniku meczu grupowego (leg / koniec / korekta / undo).
     */
    public function pushFromGroupGame(Game $game, bool $includeStandings): void
    {
        $tournamentId = (int) ($game->tournament_id ?? 0);
        $groupNumber = (int) ($game->group_number ?? 0);
        if ($tournamentId < 1 || $groupNumber < 1) {
            return;
        }

        $status = $game->status instanceof GameStatus
            ? $game->status->value
            : (string) $game->status;

        $payload = [
            'tournamentId' => $tournamentId,
            'groupNumber' => $groupNumber,
            'includeStandings' => $includeStandings,
            'game' => [
                'id' => (int) $game->id,
                'player1Id' => (int) $game->player1_id,
                'player2Id' => (int) $game->player2_id,
                'player1Score' => (int) ($game->player1_score ?? 0),
                'player2Score' => (int) ($game->player2_score ?? 0),
                'status' => $status,
            ],
            'standings' => null,
            'playoffHighlight' => null,
        ];

        if ($includeStandings) {
            $payload['standings'] = $this->standingsForGroup($tournamentId, $groupNumber);
            $payload['playoffHighlight'] = $this->playoffHighlightForGroup($tournamentId, $groupNumber);
        }

        broadcast(new TournamentGroupMatrixUpdated($tournamentId, $payload));
    }

    /**
     * Snapshot całej fazy grupowej — polling fallback.
     *
     * @return array{tournamentId: int, games: list<array<string, mixed>>, standingsByGroup: array<int, list<array<string, mixed>>>, playoffHighlights: array<int, array<string, mixed>>}
     */
    public function snapshot(int $tournamentId): array
    {
        $games = $this->gameRepository->getAllForTournament(
            $tournamentId,
            ['id', 'group_number', 'player1_id', 'player2_id', 'player1_score', 'player2_score', 'status'],
        );

        $gamePayload = $games->map(function (Game $game) {
            $status = $game->status instanceof GameStatus
                ? $game->status->value
                : (string) $game->status;

            return [
                'id' => (int) $game->id,
                'groupNumber' => (int) $game->group_number,
                'player1Id' => (int) $game->player1_id,
                'player2Id' => (int) $game->player2_id,
                'player1Score' => (int) ($game->player1_score ?? 0),
                'player2Score' => (int) ($game->player2_score ?? 0),
                'status' => $status,
            ];
        })->values()->all();

        $standingsByGroup = [];
        $groupNumbers = $games->pluck('group_number')->unique()->filter()->map(fn ($n) => (int) $n);
        foreach ($groupNumbers as $groupNumber) {
            $standingsByGroup[$groupNumber] = $this->standingsForGroup($tournamentId, $groupNumber);
        }

        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId, [
            'games.player1',
            'games.player2',
            'games.winner',
            'groupStandings.player',
        ]);
        $playoffHighlights = [];
        if ($tournament !== null) {
            $viewModel = new TournamentDataViewModel($tournament);
            $playoffHighlights = $viewModel->groupPlayoffHighlights();
        }

        return [
            'tournamentId' => $tournamentId,
            'games' => $gamePayload,
            'standingsByGroup' => $standingsByGroup,
            'playoffHighlights' => $playoffHighlights,
        ];
    }

    /**
     * @return list<array{playerId: int, gamesWon: int, gamesLost: int, matchUnitsDifference: int, points: int, place: int}>
     */
    private function standingsForGroup(int $tournamentId, int $groupNumber): array
    {
        return $this->groupStandingRepository->getOrderedByPlaceForGroup($tournamentId, $groupNumber)
            ->map(fn (GroupStanding $s) => [
                'playerId' => (int) $s->player_id,
                'gamesWon' => (int) $s->games_won,
                'gamesLost' => (int) $s->games_lost,
                'matchUnitsDifference' => (int) $s->match_units_difference,
                'points' => (int) $s->points,
                'place' => (int) $s->place,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{complete: bool, advanceCount: int, advancingPlayerIds: list<int>}
     */
    private function playoffHighlightForGroup(int $tournamentId, int $groupNumber): array
    {
        $tournament = $this->tournamentRepository->findModelOrNull($tournamentId, [
            'games.player1',
            'games.player2',
            'games.winner',
            'groupStandings.player',
        ]);
        if ($tournament === null) {
            return [
                'complete' => false,
                'advanceCount' => 0,
                'advancingPlayerIds' => [],
            ];
        }

        $viewModel = new TournamentDataViewModel($tournament);
        $all = $viewModel->groupPlayoffHighlights();

        return $all[$groupNumber] ?? [
            'complete' => false,
            'advanceCount' => 0,
            'advancingPlayerIds' => [],
        ];
    }

    public function pushFromGroupGameAfterCommit(Game $game, bool $includeStandings): void
    {
        $gameId = (int) $game->id;
        $include = $includeStandings;

        $run = function () use ($gameId, $include) {
            $fresh = $this->gameRepository->findModelOrNull($gameId);
            if ($fresh === null) {
                return;
            }
            $this->pushFromGroupGame($fresh, $include);
        };

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($run);
        } else {
            $run();
        }
    }
}
