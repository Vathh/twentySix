<?php

namespace App\Services\Tournament;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Repositories\GameRepository;
use App\Repositories\GroupStandingRepository;
use App\Repositories\TournamentRepository;
use App\Services\LoginCodeService;
use App\Services\PointSchemeService;
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
        private PointSchemeService      $pointSchemeService
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
        $playersAmount = count($playerIds);

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
            return DB::transaction(function () use ($tournamentId, $gamesToInsert, $groups, $playersAmount) {
                if ($this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId))
                {
                    $this->updatePointSchemeId($tournamentId, $playersAmount);
                    $this->groupStandingRepository->createEmptyStandings($tournamentId, $groups);
                    $this->gameRepository->createGames($gamesToInsert);
                    $this->loginCodeService->generateCodes(count($groups), $tournamentId);
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::GROUP);
                    return true;
                }
                return false;
            });
        } catch (Throwable $e) {
            throw new RuntimeException('Nie udało się stworzyć grup', $e);
        }
    }

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

    private function updatePointSchemeId(int $tournamentId, int $playersAmount): void
    {
        $pointScheme = $this->pointSchemeService->findByPlayersAmount($playersAmount);

        $this->tournamentRepository->updatePointSchemeId($tournamentId, $pointScheme->id);
    }
}
