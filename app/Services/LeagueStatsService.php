<?php

namespace App\Services;

use App\Repositories\LeaguePlayerStatRepository;
use Illuminate\Support\Collection;

class LeagueStatsService
{
    public function __construct(
        private LeaguePlayerStatRepository $leaguePlayerStatRepository
    ) {
    }

    /**
     * Top 40 zawodników ligi (z cache). Przy braku wpisów – przelicza i zwraca.
     *
     * @return Collection<int, object{place: int, player_id: int, player_name: string, points: int, count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function getTop40(int $leagueId): Collection
    {
        $top = $this->leaguePlayerStatRepository->getTop40($leagueId);
        if ($top->isEmpty()) {
            $this->recalculateForLeague($leagueId);
            $top = $this->leaguePlayerStatRepository->getTop40($leagueId);
        }
        return $top;
    }

    /**
     * Przelicza statystyki ligowe (punkty + achievementy) i zapisuje w league_player_stats.
     */
    public function recalculateForLeague(int $leagueId): void
    {
        $tournamentIds = $this->leaguePlayerStatRepository->getTournamentIdsForLeague($leagueId);
        $pointsByPlayer = $this->leaguePlayerStatRepository->getPointsByPlayerForTournaments($tournamentIds);
        $achievementsByPlayer = $this->leaguePlayerStatRepository->getAchievementsAggregatedForTournaments($tournamentIds);

        $playerIds = array_unique(array_merge(array_keys($pointsByPlayer), array_keys($achievementsByPlayer)));
        $rows = [];
        foreach ($playerIds as $playerId) {
            $points = $pointsByPlayer[$playerId] ?? 0;
            $ach = $achievementsByPlayer[$playerId] ?? [
                'count_max' => 0,
                'count_170_plus' => 0,
                'count_qf' => 0,
                'count_hf' => 0,
                'best_qf' => null,
                'best_hf' => null,
            ];
            $rows[] = [
                'player_id' => $playerId,
                'points' => $points,
                'count_max' => $ach['count_max'],
                'count_170_plus' => $ach['count_170_plus'],
                'count_qf' => $ach['count_qf'],
                'count_hf' => $ach['count_hf'],
                'best_qf' => $ach['best_qf'],
                'best_hf' => $ach['best_hf'],
            ];
        }
        $this->leaguePlayerStatRepository->replaceForLeague($leagueId, $rows);
    }
}
