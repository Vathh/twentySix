<?php

namespace App\Repositories\Game;

use Illuminate\Support\Facades\DB;

class HeadToHeadRepository
{
    /**
     * Zakończone mecze 1 na 1 między dwoma zawodnikami (grupa, play-off, liga, szybka gra).
     * Szybka gra z więcej niż dwoma wynikami to FFA i nie wchodzi do bilansu.
     *
     * @return list<array{
     *     kind: string,
     *     id: int,
     *     player1_id: int,
     *     player2_id: int,
     *     player1_score: int,
     *     player2_score: int,
     *     winner_id: int|null,
     *     played_at: string,
     *     event_name: string|null,
     *     organization_name: string|null
     * }>
     */
    public function meetings(int $player1Id, int $player2Id): array
    {
        $rows = array_merge(
            $this->tournamentMeetings('games', 'group', $player1Id, $player2Id),
            $this->tournamentMeetings('playoff_games', 'playoff', $player1Id, $player2Id),
            $this->leagueMeetings($player1Id, $player2Id),
            $this->quickMeetings($player1Id, $player2Id),
        );

        usort($rows, function (array $a, array $b): int {
            $byDate = strcmp($b['played_at'], $a['played_at']);
            if ($byDate !== 0) {
                return $byDate;
            }

            return $b['id'] <=> $a['id'];
        });

        return $rows;
    }

    public function quickResultCount(int $quickGameId): int
    {
        return (int) DB::table('quick_game_results')->where('quick_game_id', $quickGameId)->count();
    }

    /**
     * Średnia meczu (3 lotki) per zawodnik. Najpierw ważona z legów, a dla szybkiej gry
     * nadpisana wartością z quick_game_results, gdy jest zapisana.
     *
     * @param  list<array{kind: string, id: int, player1_id: int, player2_id: int}>  $meetings
     * @return array<string, float> klucz "{kind}:{id}:{playerId}"
     */
    public function averages(array $meetings): array
    {
        if ($meetings === []) {
            return [];
        }

        $owners = [];
        foreach ($meetings as $meeting) {
            $owners[$meeting['kind'].':'.$meeting['id']] = [$meeting['player1_id'], $meeting['player2_id']];
        }
        $ids = $this->idsByKind($meetings);
        if ($ids === null) {
            return [];
        }

        $sums = [];
        foreach ($this->legsFor($ids) as $leg) {
            $owner = $this->legOwner($leg);
            if ($owner === null || ! isset($owners[$owner])) {
                continue;
            }
            [$homeId, $awayId] = $owners[$owner];
            $this->addWeighted($sums, $owner, $homeId, $leg->player1_average, $leg->player1_darts_thrown);
            $this->addWeighted($sums, $owner, $awayId, $leg->player2_average, $leg->player2_darts_thrown);
        }

        $averages = [];
        foreach ($sums as $owner => $byPlayer) {
            foreach ($byPlayer as $playerId => $bucket) {
                if ($bucket['weight'] <= 0) {
                    continue;
                }
                $averages[$owner.':'.$playerId] = round($bucket['weighted'] / $bucket['weight'], 2);
            }
        }

        if ($ids['quick'] !== []) {
            $results = DB::table('quick_game_results')
                ->whereIn('quick_game_id', $ids['quick'])
                ->get(['quick_game_id', 'player_id', 'average']);
            foreach ($results as $result) {
                if ($result->average === null || ! is_numeric($result->average)) {
                    continue;
                }
                $averages['quick:'.(int) $result->quick_game_id.':'.(int) $result->player_id] = round((float) $result->average, 2);
            }
        }

        return $averages;
    }

    /**
     * Liczba wygranych legów per zawodnik. Klucz "{kind}:{id}:{playerId}".
     *
     * @param  list<array{kind: string, id: int}>  $meetings
     * @return array<string, int>
     */
    public function legWins(array $meetings): array
    {
        $ids = $this->idsByKind($meetings);
        if ($ids === null) {
            return [];
        }

        $rows = DB::table('game_legs')
            ->whereNotNull('winner_id')
            ->whereNotNull('finished_at')
            ->where(function ($query) use ($ids) {
                $this->scopeOwners($query, $ids);
            })
            ->get([
                'game_id',
                'playoff_game_id',
                'league_game_id',
                'quick_game_id',
                'winner_id',
            ]);

        $counts = [];
        foreach ($rows as $leg) {
            $owner = $this->legOwner($leg);
            if ($owner === null) {
                continue;
            }
            $key = $owner.':'.(int) $leg->winner_id;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tournamentMeetings(string $table, string $kind, int $left, int $right): array
    {
        $rows = DB::table($table)
            ->join('tournaments', 'tournaments.id', '=', $table.'.tournament_id')
            ->leftJoin('organizations as org_direct', 'org_direct.id', '=', 'tournaments.organization_id')
            ->leftJoin('seasons', 'seasons.id', '=', 'tournaments.season_id')
            ->leftJoin('organizations as org_season', 'org_season.id', '=', 'seasons.organization_id')
            ->where($table.'.status', 'finished')
            ->where(function ($query) use ($table, $left, $right) {
                $this->eitherOrder($query, $table, $left, $right);
            })
            ->get([
                $table.'.id',
                $table.'.player1_id',
                $table.'.player2_id',
                $table.'.player1_score',
                $table.'.player2_score',
                $table.'.sets_to_win_match',
                $table.'.winner_id',
                DB::raw('COALESCE('.$table.'.finished_at, '.$table.'.updated_at, '.$table.'.created_at) as played_at'),
                'tournaments.name as event_name',
                DB::raw('COALESCE(org_direct.name, org_season.name) as organization_name'),
            ]);

        return $rows->map(fn ($row) => $this->mapRow($kind, $row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leagueMeetings(int $left, int $right): array
    {
        $rows = DB::table('league_games')
            ->join('league_seasons', 'league_seasons.id', '=', 'league_games.league_season_id')
            ->join('leagues', 'leagues.id', '=', 'league_seasons.league_id')
            ->leftJoin('organizations', 'organizations.id', '=', 'leagues.organization_id')
            ->where('league_games.status', 'finished')
            ->where(function ($query) use ($left, $right) {
                $this->eitherOrder($query, 'league_games', $left, $right);
            })
            ->get([
                'league_games.id',
                'league_games.player1_id',
                'league_games.player2_id',
                'league_games.player1_score',
                'league_games.player2_score',
                'league_games.sets_to_win_match',
                'league_games.winner_id',
                DB::raw('COALESCE(league_games.finished_at, league_games.updated_at, league_games.created_at) as played_at'),
                'leagues.name as event_name',
                'organizations.name as organization_name',
            ]);

        return $rows->map(fn ($row) => $this->mapRow('league', $row))->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function quickMeetings(int $left, int $right): array
    {
        $rows = DB::table('quick_games')
            ->where('quick_games.status', 'finished')
            ->where(function ($query) use ($left, $right) {
                $this->eitherOrder($query, 'quick_games', $left, $right);
            })
            ->whereRaw('(select count(*) from quick_game_results where quick_game_results.quick_game_id = quick_games.id) <= 2')
            ->get([
                'quick_games.id',
                'quick_games.player1_id',
                'quick_games.player2_id',
                'quick_games.player1_score',
                'quick_games.player2_score',
                'quick_games.sets_to_win_match',
                'quick_games.winner_id',
                DB::raw('COALESCE(quick_games.updated_at, quick_games.created_at) as played_at'),
            ]);

        return $rows->map(function ($row) {
            $row->event_name = null;
            $row->organization_name = null;

            return $this->mapRow('quick', $row);
        })->all();
    }

    /**
     * @param  array{group: list<int>, playoff: list<int>, league: list<int>, quick: list<int>}  $ids
     * @return list<object>
     */
    private function legsFor(array $ids): array
    {
        $query = DB::table('game_legs')->where(function ($query) use ($ids) {
            $this->scopeOwners($query, $ids);
        });

        return $query->get([
            'game_id',
            'playoff_game_id',
            'league_game_id',
            'quick_game_id',
            'player1_average',
            'player2_average',
            'player1_darts_thrown',
            'player2_darts_thrown',
        ])->all();
    }

    /**
     * @param  list<array{kind: string, id: int}>  $meetings
     * @return array{group: list<int>, playoff: list<int>, league: list<int>, quick: list<int>}|null
     */
    private function idsByKind(array $meetings): ?array
    {
        $ids = ['group' => [], 'playoff' => [], 'league' => [], 'quick' => []];
        foreach ($meetings as $meeting) {
            $ids[$meeting['kind']][] = $meeting['id'];
        }
        if ($ids['group'] === [] && $ids['playoff'] === [] && $ids['league'] === [] && $ids['quick'] === []) {
            return null;
        }

        return $ids;
    }

    /**
     * @param  array{group: list<int>, playoff: list<int>, league: list<int>, quick: list<int>}  $ids
     */
    private function scopeOwners($query, array $ids): void
    {
        $started = false;
        foreach ([
            'group' => 'game_id',
            'playoff' => 'playoff_game_id',
            'league' => 'league_game_id',
            'quick' => 'quick_game_id',
        ] as $kind => $column) {
            if ($ids[$kind] === []) {
                continue;
            }
            $method = $started ? 'orWhereIn' : 'whereIn';
            $query->{$method}($column, $ids[$kind]);
            $started = true;
        }
        if (! $started) {
            $query->whereRaw('0 = 1');
        }
    }

    private function legOwner(object $leg): ?string
    {
        if ($leg->game_id !== null) {
            return 'group:'.(int) $leg->game_id;
        }
        if ($leg->playoff_game_id !== null) {
            return 'playoff:'.(int) $leg->playoff_game_id;
        }
        if ($leg->league_game_id !== null) {
            return 'league:'.(int) $leg->league_game_id;
        }
        if ($leg->quick_game_id !== null) {
            return 'quick:'.(int) $leg->quick_game_id;
        }

        return null;
    }

    /**
     * @param  array<string, array<int, array{weighted: float, weight: int}>>  $sums
     */
    private function addWeighted(array &$sums, string $owner, int $playerId, mixed $average, mixed $darts): void
    {
        if ($average === null || ! is_numeric($average)) {
            return;
        }
        $weight = is_numeric($darts) && (int) $darts > 0 ? (int) $darts : 1;
        $sums[$owner][$playerId]['weighted'] = ($sums[$owner][$playerId]['weighted'] ?? 0) + ((float) $average * $weight);
        $sums[$owner][$playerId]['weight'] = ($sums[$owner][$playerId]['weight'] ?? 0) + $weight;
    }

    private function eitherOrder($query, string $table, int $left, int $right): void
    {
        $query->where(function ($query) use ($table, $left, $right) {
            $query->where($table.'.player1_id', $left)->where($table.'.player2_id', $right);
        })->orWhere(function ($query) use ($table, $left, $right) {
            $query->where($table.'.player1_id', $right)->where($table.'.player2_id', $left);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRow(string $kind, object $row): array
    {
        return [
            'kind' => $kind,
            'id' => (int) $row->id,
            'player1_id' => (int) $row->player1_id,
            'player2_id' => (int) $row->player2_id,
            'player1_score' => $row->player1_score !== null ? (int) $row->player1_score : 0,
            'player2_score' => $row->player2_score !== null ? (int) $row->player2_score : 0,
            'sets_to_win_match' => isset($row->sets_to_win_match) ? (int) $row->sets_to_win_match : 1,
            'winner_id' => $row->winner_id !== null ? (int) $row->winner_id : null,
            'played_at' => $row->played_at !== null ? (string) $row->played_at : '',
            'event_name' => isset($row->event_name) && $row->event_name !== null && $row->event_name !== ''
                ? (string) $row->event_name
                : null,
            'organization_name' => isset($row->organization_name) && $row->organization_name !== null && $row->organization_name !== ''
                ? (string) $row->organization_name
                : null,
        ];
    }
}
