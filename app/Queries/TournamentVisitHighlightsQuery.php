<?php

namespace App\Queries;

use Illuminate\Support\Facades\DB;

/**
 * 180, 170+ i HF (checkout ≥ 100) z wizyt scoringu — grupa i playoff.
 * Źródło prawdy dla zakładki osiągnięć, gdy mecz był sędziowany na webie
 * albo tablet nie dosłał osobnego POST-a achievementów.
 */
class TournamentVisitHighlightsQuery
{
    /**
     * @param  list<int>  $tournamentIds
     * @return array{
     *     tournamentIdsWithVisits: list<int>,
     *     byPlayer: array<int, array{max: int, one_seventy: int, hf: list<int>}>
     * }
     */
    public function forTournaments(array $tournamentIds): array
    {
        $tournamentIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tournamentIds),
            static fn (int $id) => $id > 0,
        )));

        if ($tournamentIds === []) {
            return [
                'tournamentIdsWithVisits' => [],
                'byPlayer' => [],
            ];
        }

        $rows = DB::table('game_visits as gv')
            ->join('game_legs as gl', 'gl.id', '=', 'gv.game_leg_id')
            ->leftJoin('games as g', 'g.id', '=', 'gl.game_id')
            ->leftJoin('playoff_games as pg', 'pg.id', '=', 'gl.playoff_game_id')
            ->where('gv.is_voided', false)
            ->where(function ($q) use ($tournamentIds) {
                $q->whereIn('g.tournament_id', $tournamentIds)
                    ->orWhereIn('pg.tournament_id', $tournamentIds);
            })
            ->orderBy('gv.id')
            ->get([
                'gv.player_id',
                'gv.score',
                'gv.closed_leg',
                'gv.bust',
                'g.tournament_id as group_tournament_id',
                'pg.tournament_id as playoff_tournament_id',
            ]);

        $tournamentIdsWithVisits = [];
        $byPlayer = [];

        foreach ($rows as $row) {
            $tournamentId = (int) ($row->group_tournament_id ?? $row->playoff_tournament_id ?? 0);
            if ($tournamentId > 0) {
                $tournamentIdsWithVisits[$tournamentId] = $tournamentId;
            }

            $playerId = (int) $row->player_id;
            if (! isset($byPlayer[$playerId])) {
                $byPlayer[$playerId] = [
                    'max' => 0,
                    'one_seventy' => 0,
                    'hf' => [],
                ];
            }

            if ($row->bust) {
                continue;
            }

            $score = (int) $row->score;
            if ($score === 180) {
                $byPlayer[$playerId]['max']++;
            } elseif ($score >= 170 && $score < 180) {
                $byPlayer[$playerId]['one_seventy']++;
            }

            if ($row->closed_leg && $score >= 100) {
                $byPlayer[$playerId]['hf'][] = $score;
            }
        }

        return [
            'tournamentIdsWithVisits' => array_values($tournamentIdsWithVisits),
            'byPlayer' => $byPlayer,
        ];
    }

    /**
     * QF: lotki na wygranym, zamkniętym legu (grupa i playoff).
     *
     * @param  list<int>  $tournamentIds
     * @return array<int, list<int>> player_id => lista darts_thrown
     */
    public function quickFinishesForTournaments(array $tournamentIds): array
    {
        $tournamentIds = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tournamentIds),
            static fn (int $id) => $id > 0,
        )));

        if ($tournamentIds === []) {
            return [];
        }

        $rows = DB::table('game_leg_player_stats as glps')
            ->join('game_legs as gl', 'gl.id', '=', 'glps.game_leg_id')
            ->leftJoin('games as g', 'g.id', '=', 'gl.game_id')
            ->leftJoin('playoff_games as pg', 'pg.id', '=', 'gl.playoff_game_id')
            ->where(function ($q) use ($tournamentIds) {
                $q->whereIn('g.tournament_id', $tournamentIds)
                    ->orWhereIn('pg.tournament_id', $tournamentIds);
            })
            ->whereColumn('gl.winner_id', 'glps.player_id')
            ->whereNotNull('gl.finished_at')
            ->whereNotNull('glps.darts_thrown')
            ->where('glps.darts_thrown', '<', 20)
            ->orderBy('glps.darts_thrown')
            ->get(['glps.player_id', 'glps.darts_thrown']);

        $byPlayer = [];
        foreach ($rows as $row) {
            $byPlayer[(int) $row->player_id][] = (int) $row->darts_thrown;
        }

        return $byPlayer;
    }
}
