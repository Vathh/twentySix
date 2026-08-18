<?php

namespace App\Repositories\Stats;

use App\Enums\AchievementType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agregaty punktów / achievementów po liście turniejów (organizacja, sezon).
 */
class TournamentAggregateRepository
{
    /**
     * @return array<int, int>
     */
    public function getTournamentIdsForSeason(int $seasonId): array
    {
        return DB::table('tournaments')
            ->where('season_id', $seasonId)
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $tournamentIds
     * @return array<int, int> player_id => points
     */
    public function getPointsByPlayerForTournaments(array $tournamentIds): array
    {
        if ($tournamentIds === []) {
            return [];
        }

        return DB::table('tournament_results')
            ->whereIn('tournament_id', $tournamentIds)
            ->selectRaw('player_id, COALESCE(SUM(points), 0) as total')
            ->groupBy('player_id')
            ->pluck('total', 'player_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * @param  array<int, int>  $tournamentIds
     * @return array<int, array{count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function getAchievementsAggregatedForTournaments(array $tournamentIds): array
    {
        if ($tournamentIds === []) {
            return [];
        }

        $achievements = DB::table('achievements')
            ->whereIn('tournament_id', $tournamentIds)
            ->whereNotNull('player_id')
            ->select('player_id', 'type', 'value')
            ->get();

        $byPlayer = [];
        foreach ($achievements as $a) {
            $pid = (int) $a->player_id;
            if (! isset($byPlayer[$pid])) {
                $byPlayer[$pid] = [
                    'count_max' => 0,
                    'count_170_plus' => 0,
                    'count_qf' => 0,
                    'count_hf' => 0,
                    'best_qf' => null,
                    'best_hf' => null,
                ];
            }
            $type = $a->type;
            $value = $a->value !== null ? (int) $a->value : null;
            if ($type === AchievementType::MAX->value) {
                $byPlayer[$pid]['count_max']++;
            } elseif ($type === AchievementType::ONE_SEVENTY->value) {
                $byPlayer[$pid]['count_170_plus']++;
            } elseif ($type === AchievementType::QF->value) {
                $byPlayer[$pid]['count_qf']++;
                if ($value !== null && ($byPlayer[$pid]['best_qf'] === null || $value < $byPlayer[$pid]['best_qf'])) {
                    $byPlayer[$pid]['best_qf'] = $value;
                }
            } elseif ($type === AchievementType::HF->value) {
                $byPlayer[$pid]['count_hf']++;
                if ($value !== null && ($byPlayer[$pid]['best_hf'] === null || $value > $byPlayer[$pid]['best_hf'])) {
                    $byPlayer[$pid]['best_hf'] = $value;
                }
            }
        }

        return $byPlayer;
    }

    /**
     * Pełny ranking (bez limitu) dla podanych turniejów.
     *
     * @param  array<int, int>  $tournamentIds
     * @return Collection<int, object{place: int, player_id: int, player_name: string, user_id: ?int, points: int, count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function buildStandingsForTournaments(array $tournamentIds): Collection
    {
        $pointsByPlayer = $this->getPointsByPlayerForTournaments($tournamentIds);
        $achievementsByPlayer = $this->getAchievementsAggregatedForTournaments($tournamentIds);
        $playerIds = array_values(array_unique(array_merge(
            array_keys($pointsByPlayer),
            array_keys($achievementsByPlayer),
        )));

        if ($playerIds === []) {
            return collect();
        }

        $players = DB::table('players')
            ->whereIn('id', $playerIds)
            ->get(['id', 'name', 'user_id'])
            ->keyBy('id');

        $rows = [];
        foreach ($playerIds as $playerId) {
            $player = $players->get($playerId);
            if ($player === null) {
                continue;
            }
            $ach = $achievementsByPlayer[$playerId] ?? [
                'count_max' => 0,
                'count_170_plus' => 0,
                'count_qf' => 0,
                'count_hf' => 0,
                'best_qf' => null,
                'best_hf' => null,
            ];
            $rows[] = [
                'player_id' => (int) $playerId,
                'player_name' => (string) $player->name,
                'user_id' => $player->user_id !== null ? (int) $player->user_id : null,
                'points' => (int) ($pointsByPlayer[$playerId] ?? 0),
                'count_max' => (int) $ach['count_max'],
                'count_170_plus' => (int) $ach['count_170_plus'],
                'count_qf' => (int) $ach['count_qf'],
                'count_hf' => (int) $ach['count_hf'],
                'best_qf' => $ach['best_qf'] !== null ? (int) $ach['best_qf'] : null,
                'best_hf' => $ach['best_hf'] !== null ? (int) $ach['best_hf'] : null,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            return $a['player_id'] <=> $b['player_id'];
        });

        $place = 1;

        return collect($rows)->map(function (array $row) use (&$place) {
            return (object) [
                'place' => $place++,
                'player_id' => $row['player_id'],
                'player_name' => $row['player_name'],
                'user_id' => $row['user_id'],
                'points' => $row['points'],
                'count_max' => $row['count_max'],
                'count_170_plus' => $row['count_170_plus'],
                'count_qf' => $row['count_qf'],
                'count_hf' => $row['count_hf'],
                'best_qf' => $row['best_qf'],
                'best_hf' => $row['best_hf'],
            ];
        });
    }
}
