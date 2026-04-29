<?php

namespace App\Repositories\League;

use App\Enums\AchievementType;
use App\Models\LeaguePlayerStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaguePlayerStatRepository
{
    private const TOP_LIMIT = 40;

    /**
     * ID turniejów należących do ligi (wszystkie sezony).
     *
     * @return array<int, int>
     */
    public function getTournamentIdsForLeague(int $leagueId): array
    {
        return DB::table('tournaments')
            ->join('seasons', 'seasons.id', '=', 'tournaments.season_id')
            ->where('seasons.league_id', $leagueId)
            ->pluck('tournaments.id')
            ->all();
    }

    /**
     * Suma punktów z tournament_results per player_id dla podanych turniejów.
     *
     * @param array<int, int> $tournamentIds
     * @return array<int, int> player_id => points
     */
    public function getPointsByPlayerForTournaments(array $tournamentIds): array
    {
        if (empty($tournamentIds)) {
            return [];
        }
        $rows = DB::table('tournament_results')
            ->whereIn('tournament_id', $tournamentIds)
            ->selectRaw('player_id, COALESCE(SUM(points), 0) as total')
            ->groupBy('player_id')
            ->pluck('total', 'player_id');
        return $rows->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Agregacja achievementów (max, 170+, qf, hf) per player dla podanych turniejów.
     * Zwraca dla każdego player_id: count_max, count_170_plus, count_qf, count_hf, best_qf (min value), best_hf (max value).
     *
     * @param array<int, int> $tournamentIds
     * @return array<int, array{count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function getAchievementsAggregatedForTournaments(array $tournamentIds): array
    {
        if (empty($tournamentIds)) {
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
            if (!isset($byPlayer[$pid])) {
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
     * Zastępuje wszystkie wpisy statystyk ligowych dla ligi danymi z tablicy.
     *
     * @param array<int, array{player_id: int, points: int, count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}> $rows
     */
    public function replaceForLeague(int $leagueId, array $rows): void
    {
        LeaguePlayerStat::where('league_id', $leagueId)->delete();
        if (empty($rows)) {
            return;
        }
        $now = now();
        $insert = array_map(fn ($row) => [
            'league_id' => $leagueId,
            'player_id' => $row['player_id'],
            'points' => $row['points'],
            'count_max' => $row['count_max'],
            'count_170_plus' => $row['count_170_plus'],
            'count_qf' => $row['count_qf'],
            'count_hf' => $row['count_hf'],
            'best_qf' => $row['best_qf'] ?? null,
            'best_hf' => $row['best_hf'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $rows);
        LeaguePlayerStat::insert($insert);
    }

    /**
     * Top 40 zawodników ligi według punktów (z nazwą gracza).
     *
     * @return Collection<int, object{place: int, player_id: int, player_name: string, points: int, count_max: int, count_170_plus: int, count_qf: int, count_hf: int, best_qf: ?int, best_hf: ?int}>
     */
    public function getTop40(int $leagueId): Collection
    {
        $rows = LeaguePlayerStat::query()
            ->join('players', 'players.id', '=', 'league_player_stats.player_id')
            ->where('league_player_stats.league_id', $leagueId)
            ->orderByDesc('league_player_stats.points')
            ->limit(self::TOP_LIMIT)
            ->get([
                'league_player_stats.player_id',
                'players.name as player_name',
                'league_player_stats.points',
                'league_player_stats.count_max',
                'league_player_stats.count_170_plus',
                'league_player_stats.count_qf',
                'league_player_stats.count_hf',
                'league_player_stats.best_qf',
                'league_player_stats.best_hf',
            ]);
        $place = 1;
        return $rows->map(function ($row) use (&$place) {
            $obj = (object) [
                'place' => $place++,
                'player_id' => (int) $row->player_id,
                'player_name' => $row->player_name,
                'points' => (int) $row->points,
                'count_max' => (int) $row->count_max,
                'count_170_plus' => (int) $row->count_170_plus,
                'count_qf' => (int) $row->count_qf,
                'count_hf' => (int) $row->count_hf,
                'best_qf' => $row->best_qf !== null ? (int) $row->best_qf : null,
                'best_hf' => $row->best_hf !== null ? (int) $row->best_hf : null,
            ];
            return $obj;
        });
    }
}











