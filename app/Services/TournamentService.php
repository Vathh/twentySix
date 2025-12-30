<?php

namespace App\Services;

use App\Domain\TournamentDomain;
use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Repositories\GameRepository;
use App\Repositories\GroupStandingRepository;
use App\Repositories\LoginCodeRepository;
use App\Repositories\TournamentRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TournamentService
{

    public function __construct(
        private TournamentRepository    $tournamentRepository,
        private GameRepository          $gameRepository,
        private GroupStandingRepository $groupStandingRepository,
        private LoginCodeService        $loginCodeService,
    )
    {
    }

    public function getAll(): Collection
    {
        return $this->tournamentRepository->getAll()
                                            ->sortByDesc(fn($tournament) => $tournament->updatedAt)
                                            ->values();
    }

    public function create(
        int     $seasonId,
        string  $name,
        ?string $date = null
    ): void
    {
        $this->tournamentRepository->create($seasonId, $name, $date);
    }

    public function tryCreateGroupGames(int $tournamentId, array $playerIds, int $groupsCount): bool
    {
        $groups = $this->createGroups($playerIds, $groupsCount);

        $gamesToInsert = [];

        foreach ($groups as $groupIndex => $group) {
            foreach ($this->generateGamesForGroup($group) as $game) {
                $gamesToInsert[] = [
                    'tournament_id' => $tournamentId,
                    'player1_id' => $game['player1_id'],
                    'player2_id' => $game['player2_id'],
                    'player1_score' => 0,
                    'player2_score' => 0,
                    'group_number' => $groupIndex + 1,
                    'status' => GameStatus::SCHEDULED,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        try {
            return DB::transaction(function () use ($tournamentId, $gamesToInsert, $groups) {
                if ($this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId)) {
                    $this->groupStandingRepository->createEmptyStandings($tournamentId, $groups);
                    $this->gameRepository->createGames($gamesToInsert);
                    $this->loginCodeService->generateCodes(count($groups), $tournamentId);
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::GROUP);
                    return true;
                }
                return false;
            });
        } catch (Throwable $e) {
            throw new RuntimeException('Nie udało się stworzyć grup', 0, $e);
        }
    }

    public function

    private function generateGamesForGroup(array $group): array
    {
        $games = [];

        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) {
                $games[] = ['player1_id' => $group[$i], 'player2_id' => $group[$j]];
            }
        }

        return $games;
    }

    private function createGroups(array $playerIds, int $groupsCount): array
    {
        shuffle($playerIds);

        $result = array_fill(0, $groupsCount, []);

        foreach ($playerIds as $index => $playerId) {
            $groupNumber = $index % $groupsCount;

            $result[$groupNumber][] = $playerId;
        }

        return $result;
    }
}
