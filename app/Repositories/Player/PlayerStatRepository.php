<?php

namespace App\Repositories\Player;

use App\Models\Player\PlayerStat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PlayerStatRepository
{
    /**
     * Zwraca zapisane statystyki gracza z tabeli player_stats.
     */
    public function findByPlayerId(int $playerId): ?PlayerStat
    {
        return PlayerStat::query()->where('player_id', $playerId)->first();
    }

    /**
     * Zapisuje lub aktualizuje statystyki gracza w player_stats.
     *
     * @param array{games: int, avg_three_darts: ?float, highest_hf: ?int, fastest_qf: ?int, count_max: int, count_170_plus: int, count_hf: int, count_qf: int} $quickStats
     * @param array{games: int, avg_three_darts: ?float, highest_hf: ?int, fastest_qf: ?int, count_max: int, count_170_plus: int, count_hf: int, count_qf: int} $tournamentStats
     */
    public function upsert(int $playerId, array $quickStats, array $tournamentStats): void
    {
        PlayerStat::query()->updateOrCreate(
            ['player_id' => $playerId],
            [
                'quick_games' => $quickStats['games'],
                'quick_avg_three_darts' => $quickStats['avg_three_darts'],
                'quick_highest_hf' => $quickStats['highest_hf'],
                'quick_fastest_qf' => $quickStats['fastest_qf'],
                'quick_count_max' => $quickStats['count_max'],
                'quick_count_170_plus' => $quickStats['count_170_plus'],
                'quick_count_hf' => $quickStats['count_hf'],
                'quick_count_qf' => $quickStats['count_qf'],
                'tournament_games' => $tournamentStats['games'],
                'tournament_avg_three_darts' => $tournamentStats['avg_three_darts'],
                'tournament_highest_hf' => $tournamentStats['highest_hf'],
                'tournament_fastest_qf' => $tournamentStats['fastest_qf'],
                'tournament_count_max' => $tournamentStats['count_max'],
                'tournament_count_170_plus' => $tournamentStats['count_170_plus'],
                'tournament_count_hf' => $tournamentStats['count_hf'],
                'tournament_count_qf' => $tournamentStats['count_qf'],
            ]
        );
    }

    /**
     * Dane do wyliczenia statystyk quick (quick_game_results + achievements bez tournament_id).
     *
     * @return array{results: object|null, achievements: Collection<int, object>}
     */
    public function getDataForQuickStats(int $playerId): array
    {
        $results = DB::table('quick_game_results')
            ->where('player_id', $playerId)
            ->selectRaw('COUNT(*) as games, AVG(average) as avg_average')
            ->first();

        $achievements = DB::table('achievements')
            ->where('player_id', $playerId)
            ->whereNull('tournament_id')
            ->get();

        return ['results' => $results, 'achievements' => $achievements];
    }

    /**
     * Dane do wyliczenia statystyk turniejowych.
     *
     * Średnia / max / HF / QF z scoring API (game_leg_player_stats + game_visits).
     * Achievementy — legacy / uzupełnienie gdy brak danych scoringu.
     *
     * @return array{
     *     games_count: int,
     *     avg_from_legs: float|null,
     *     has_scoring_data: bool,
     *     scoring: array{
     *         count_max: int,
     *         count_170_plus: int,
     *         count_hf: int,
     *         count_qf: int,
     *         highest_hf: ?int,
     *         fastest_qf: ?int
     *     },
     *     achievements: Collection<int, object>
     * }
     */
    public function getDataForTournamentStats(int $playerId): array
    {
        $gamesCount = DB::table('games')
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('status', 'finished')
            ->count();

        $gamesCount += DB::table('playoff_games')
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('status', 'finished')
            ->count();

        $scoring = $this->getTournamentScoringAggregates($playerId);
        $avgFromLegs = $scoring['avg_three_darts']
            ?? $this->getLegacyTournamentAverageFromLegs($playerId);

        $achievements = DB::table('achievements')
            ->where('player_id', $playerId)
            ->whereNotNull('tournament_id')
            ->get();

        return [
            'games_count' => $gamesCount,
            'avg_from_legs' => $avgFromLegs,
            'has_scoring_data' => $scoring['has_scoring_data'],
            'scoring' => [
                'count_max' => $scoring['count_max'],
                'count_170_plus' => $scoring['count_170_plus'],
                'count_hf' => $scoring['count_hf'],
                'count_qf' => $scoring['count_qf'],
                'highest_hf' => $scoring['highest_hf'],
                'fastest_qf' => $scoring['fastest_qf'],
            ],
            'achievements' => $achievements,
        ];
    }

    /**
     * Agregaty z wizyt / średnich legów scoringu (grupa + playoff).
     *
     * @return array{
     *     has_scoring_data: bool,
     *     avg_three_darts: ?float,
     *     count_max: int,
     *     count_170_plus: int,
     *     count_hf: int,
     *     count_qf: int,
     *     highest_hf: ?int,
     *     fastest_qf: ?int
     * }
     */
    private function getTournamentScoringAggregates(int $playerId): array
    {
        $empty = [
            'has_scoring_data' => false,
            'avg_three_darts' => null,
            'count_max' => 0,
            'count_170_plus' => 0,
            'count_hf' => 0,
            'count_qf' => 0,
            'highest_hf' => null,
            'fastest_qf' => null,
        ];

        $avg = DB::table('game_leg_player_stats as glps')
            ->join('game_legs as gl', 'gl.id', '=', 'glps.game_leg_id')
            ->where('glps.player_id', $playerId)
            ->whereNotNull('glps.leg_average')
            ->whereNotNull('gl.finished_at')
            ->where(function ($q) {
                $q->whereNotNull('gl.game_id')->orWhereNotNull('gl.playoff_game_id');
            })
            ->avg('glps.leg_average');

        $visitRows = DB::table('game_visits as gv')
            ->join('game_legs as gl', 'gl.id', '=', 'gv.game_leg_id')
            ->where('gv.player_id', $playerId)
            ->where('gv.is_voided', false)
            ->where('gv.bust', false)
            ->where(function ($q) {
                $q->whereNotNull('gl.game_id')->orWhereNotNull('gl.playoff_game_id');
            })
            ->select('gv.score', 'gv.closed_leg')
            ->get();

        $qfDarts = DB::table('game_leg_player_stats as glps')
            ->join('game_legs as gl', 'gl.id', '=', 'glps.game_leg_id')
            ->where('glps.player_id', $playerId)
            ->whereColumn('gl.winner_id', 'glps.player_id')
            ->whereNotNull('glps.darts_thrown')
            ->where('glps.darts_thrown', '<', 20)
            ->whereNotNull('gl.finished_at')
            ->where(function ($q) {
                $q->whereNotNull('gl.game_id')->orWhereNotNull('gl.playoff_game_id');
            })
            ->pluck('glps.darts_thrown');

        $hasScoringData = $avg !== null || $visitRows->isNotEmpty() || $qfDarts->isNotEmpty();
        if (! $hasScoringData) {
            return $empty;
        }

        $countMax = 0;
        $count170 = 0;
        $countHf = 0;
        $highestHf = null;

        foreach ($visitRows as $row) {
            $score = (int) $row->score;
            if ($score === 180) {
                $countMax++;
            } elseif ($score >= 170 && $score < 180) {
                $count170++;
            }
            if ($row->closed_leg && $score >= 100) {
                $countHf++;
                if ($highestHf === null || $score > $highestHf) {
                    $highestHf = $score;
                }
            }
        }

        $fastestQf = $qfDarts->isEmpty() ? null : (int) $qfDarts->min();

        return [
            'has_scoring_data' => true,
            'avg_three_darts' => $avg !== null ? (float) $avg : null,
            'count_max' => $countMax,
            'count_170_plus' => $count170,
            'count_hf' => $countHf,
            'count_qf' => $qfDarts->count(),
            'highest_hf' => $highestHf,
            'fastest_qf' => $fastestQf,
        ];
    }

    /**
     * Legacy: średnie z kolumn game_legs.player*_average (bulk finish sprzed scoring API).
     */
    private function getLegacyTournamentAverageFromLegs(int $playerId): ?float
    {
        $fromGames = $this->getLegAveragesFromGames($playerId);
        $fromPlayoff = $this->getLegAveragesFromPlayoff($playerId);
        $all = $fromGames->merge($fromPlayoff)->filter();
        if ($all->isEmpty()) {
            return null;
        }

        return (float) $all->avg();
    }

    private function getLegAveragesFromGames(int $playerId): Collection
    {
        $p1 = DB::table('game_legs')
            ->join('games', 'game_legs.game_id', '=', 'games.id')
            ->where('games.player1_id', $playerId)
            ->whereNotNull('game_legs.player1_average')
            ->pluck('game_legs.player1_average');
        $p2 = DB::table('game_legs')
            ->join('games', 'game_legs.game_id', '=', 'games.id')
            ->where('games.player2_id', $playerId)
            ->whereNotNull('game_legs.player2_average')
            ->pluck('game_legs.player2_average');

        return $p1->merge($p2);
    }

    private function getLegAveragesFromPlayoff(int $playerId): Collection
    {
        $p1 = DB::table('game_legs')
            ->join('playoff_games', 'game_legs.playoff_game_id', '=', 'playoff_games.id')
            ->where('playoff_games.player1_id', $playerId)
            ->whereNotNull('game_legs.player1_average')
            ->pluck('game_legs.player1_average');
        $p2 = DB::table('game_legs')
            ->join('playoff_games', 'game_legs.playoff_game_id', '=', 'playoff_games.id')
            ->where('playoff_games.player2_id', $playerId)
            ->whereNotNull('game_legs.player2_average')
            ->pluck('game_legs.player2_average');

        return $p1->merge($p2);
    }
}
