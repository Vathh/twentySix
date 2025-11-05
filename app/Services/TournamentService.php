<?php

namespace App\Services;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Repositories\GameRepository;
use App\Repositories\TournamentRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TournamentService
{

    public function __construct(
        private TournamentRepository $tournamentRepository,
        private GameRepository $gameRepository
    )
    {
    }

    public function create(
        int $seasonId,
        string  $name,
        ?string $date = null
    ): void
    {
        $this->tournamentRepository->create($seasonId, $name, $date);
    }

    public function tryCreateGames(int $tournamentId, array $playerIds, int $groupsCount): bool
    {
        $groups = $this->createGroups($playerIds, $groupsCount);

        $gamesToInsert = [];

        foreach($groups as $groupIndex => $group) {
            foreach ($this->generateGamesForGroup($group) as $game) {
                $gamesToInsert[] = [
                    'tournament_id' => $tournamentId,
                    'player1_id' => $game['player1_id'],
                    'player2_id' => $game['player2_id'],
                    'group_number' => $groupIndex + 1,
                    'status' => GameStatus::SCHEDULED,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
        }

        try {
            return DB::transaction(function () use ($tournamentId, $gamesToInsert) {
                if($this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId))
                {
                    $this->gameRepository->createGames($gamesToInsert);
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::STARTED);
                    return true;
                }
                return false;
            });
        } catch (Throwable $e) {
            throw new RuntimeException('Nie udało się stworzyć grup', 0, $e);
        }
    }

    private function generateGamesForGroup(array $group): array
    {
        $games = [];

        for($i = 0; $i < count($group); $i++) {
            for($j = $i + 1; $j < count($group); $j++) {
                $games[] = ['player1_id' => $group[$i], 'player2_id' => $group[$j]];
            }
        }

        return $games;
    }

    private function createGroups(array $playerIds, int $groupsCount): array
    {
        shuffle($playerIds);

        $result = array_fill(0, $groupsCount, []);

        foreach($playerIds as $index => $playerId) {
            $groupNumber = $index%$groupsCount;

            $result[$groupNumber][] = $playerId;
        }

        return $result;
    }
}
