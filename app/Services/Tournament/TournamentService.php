<?php

namespace App\Services\Tournament;

use App\Enums\GameStatus;
use App\Enums\TournamentStatus;
use App\Repositories\Game\GameRepository;
use App\Repositories\GroupStanding\GroupStandingRepository;
use App\Repositories\Tournament\TournamentRepository;
use App\Support\Tournament\TournamentGroupDistribution;
use App\Services\Tournament\LoginCodeService;
use App\Services\PointScheme\PointSchemeService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TournamentService
{

    public function __construct(
        private TournamentRepository      $tournamentRepository,
        private GameRepository            $gameRepository,
        private GroupStandingRepository   $groupStandingRepository,
        private LoginCodeService          $loginCodeService,
        private PointSchemeService        $pointSchemeService,
        private TournamentStartValidator  $startValidator,
    )
    {
    }

    public function getAll(): Collection
    {
        return $this->tournamentRepository->getAll()
            ->sortByDesc(
                fn ($tournament) => $tournament->date?->getTimestamp() ?? PHP_INT_MIN,
            )
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

    /**
     * @throws ValidationException
     */
    public function tryCreateGroupGames(
        int $tournamentId,
        array $playerIds,
        int $groupsCount,
        int $advancePerGroup = 2,
        ?int $tabletsCount = null,
    ): bool {
        $tabletsCount ??= $groupsCount;

        $this->startValidator->validate(
            playerCount: count($playerIds),
            groupsCount: $groupsCount,
            advancePerGroup: $advancePerGroup,
            tabletsCount: $tabletsCount,
        );

        $playersAmount = count($playerIds);

        $groups = TournamentGroupDistribution::distribute($playerIds, $groupsCount);

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
            return DB::transaction(function () use (
                $tournamentId,
                $gamesToInsert,
                $groups,
                $playersAmount,
                $groupsCount,
                $advancePerGroup,
                $tabletsCount,
            ) {
                if ($this->tournamentRepository->checkIfTournamentCanBeStarted($tournamentId)) {
                    $this->tournamentRepository->saveStartConfiguration(
                        $tournamentId,
                        $groupsCount,
                        $advancePerGroup,
                        $tabletsCount,
                    );
                    $this->updatePointSchemeId($tournamentId, $playersAmount);
                    $this->groupStandingRepository->createEmptyStandings($tournamentId, $groups);
                    $this->gameRepository->createGames($gamesToInsert);
                    $this->loginCodeService->generateCodes($tabletsCount, $tournamentId);
                    $this->tournamentRepository->changeStatus($tournamentId, TournamentStatus::GROUP);

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

        for ($i = 0; $i < count($group); $i++) {
            for ($j = $i + 1; $j < count($group); $j++) {
                $games[] = ['player1_id' => $group[$i], 'player2_id' => $group[$j]];
            }
        }

        return $games;
    }

    private function updatePointSchemeId(int $tournamentId, int $playersAmount): void
    {
        $pointScheme = $this->pointSchemeService->findByPlayersAmount($playersAmount);

        $this->tournamentRepository->updatePointSchemeId($tournamentId, $pointScheme->id);
    }
}












