<?php

namespace App\Repositories\Player;

use Illuminate\Support\Facades\DB;

class PlayerGameHistoryRepository
{
    private const PER_PAGE = 5;

    private const MAX_STUBS = 500;

    /**
     * Zwraca stronę historii meczów gracza (quick + turniejowe grupy + play-off), posortowaną od najnowszych.
     *
     * @return array{items: array<int, array{type: string, id: int|null, date: string, date_formatted: string, opponents: string, result: string, score: string|null, tournament_name: string|null}>, has_more: bool}
     */
    public function getHistoryPage(int $playerId, int $page, bool $includeTraining = false): array
    {
        $offset = ($page - 1) * self::PER_PAGE;
        $stubs = $this->fetchAllStubs($playerId, $includeTraining);
        $total = count($stubs);
        $slice = array_slice($stubs, $offset, self::PER_PAGE);
        $items = $this->resolveDetails($playerId, $slice);
        $hasMore = $offset + count($slice) < $total;

        return [
            'items' => $items,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Zbiera wszystkie „karty” meczów (typ + data + id źródła), sortuje po dacie malejąco.
     *
     * @return list<array{type: string, date: string, source_id: int, source_type: string}>
     */
    private function fetchAllStubs(int $playerId, bool $includeTraining): array
    {
        $quick = DB::table('quick_game_results')
            ->join('quick_games', 'quick_games.id', '=', 'quick_game_results.quick_game_id')
            ->where('quick_game_results.player_id', $playerId)
            ->where('quick_games.status', 'finished')
            ->selectRaw("'quick' as type, quick_games.created_at as date, quick_games.id as source_id, 'quick' as source_type")
            ->get()
            ->map(fn ($r) => ['type' => 'quick', 'date' => $r->date, 'source_id' => (int) $r->source_id, 'source_type' => 'quick'])
            ->all();

        $games = DB::table('games')
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('status', 'finished')
            ->selectRaw("'group' as type, games.updated_at as date, games.id as source_id, 'game' as source_type")
            ->get()
            ->map(fn ($r) => ['type' => 'group', 'date' => $r->date, 'source_id' => (int) $r->source_id, 'source_type' => 'game'])
            ->all();

        $playoff = DB::table('playoff_games')
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('status', 'finished')
            ->selectRaw("'playoff' as type, playoff_games.updated_at as date, playoff_games.id as source_id, 'playoff' as source_type")
            ->get()
            ->map(fn ($r) => ['type' => 'playoff', 'date' => $r->date, 'source_id' => (int) $r->source_id, 'source_type' => 'playoff'])
            ->all();

        $league = DB::table('league_games')
            ->where(function ($q) use ($playerId) {
                $q->where('player1_id', $playerId)->orWhere('player2_id', $playerId);
            })
            ->where('status', 'finished')
            ->selectRaw("'league' as type, league_games.updated_at as date, league_games.id as source_id, 'league' as source_type")
            ->get()
            ->map(fn ($r) => ['type' => 'league', 'date' => $r->date, 'source_id' => (int) $r->source_id, 'source_type' => 'league'])
            ->all();

        $training = [];
        if ($includeTraining) {
            $training = DB::table('player_game_snapshots')
                ->where('player_id', $playerId)
                ->where('source', 'training')
                ->selectRaw("'training' as type, occurred_at as date, id as source_id, 'training' as source_type")
                ->get()
                ->map(fn ($r) => ['type' => 'training', 'date' => $r->date, 'source_id' => (int) $r->source_id, 'source_type' => 'training'])
                ->all();
        }

        $merged = array_merge($quick, $games, $playoff, $league, $training);
        usort($merged, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($merged, 0, self::MAX_STUBS);
    }

    /**
     * Skróty meczów dla źródeł z badge events (liga / grupa / play-off).
     *
     * @param  list<array{source_kind: string, source_id: int, created_at?: string|null}>  $refs
     * @return array<string, array{type: string, id: int|null, date: string, date_formatted: string, opponents: string, result: string, score: string|null, tournament_name: string|null}>
     */
    public function summariesForBadgeSources(int $playerId, array $refs): array
    {
        $stubs = [];
        foreach ($refs as $ref) {
            $kind = (string) $ref['source_kind'];
            $sourceType = $kind === 'group' ? 'game' : $kind;
            $date = $ref['created_at'] ?? now()->toDateTimeString();
            $stubs[] = [
                'type' => $kind === 'group' ? 'group' : $kind,
                'date' => $date,
                'source_id' => (int) $ref['source_id'],
                'source_type' => $sourceType,
            ];
        }

        $items = $this->resolveDetails($playerId, $stubs);
        $map = [];
        foreach ($items as $i => $item) {
            $ref = $refs[$i];
            $map[$ref['source_kind'].':'.$ref['source_id']] = $item;
        }

        return $map;
    }

    /**
     * Dla wycinka stubów pobiera szczegóły (przeciwnicy, wynik, turniej).
     *
     * @param  array<int, array{type: string, date: string, source_id: int, source_type: string}>  $stubs
     * @return list<array{type: string, id: int|null, date: string, date_formatted: string, opponents: string, result: string, score: string|null, tournament_name: string|null}>
     */
    private function resolveDetails(int $playerId, array $stubs): array
    {
        $items = [];
        foreach ($stubs as $stub) {
            if ($stub['source_type'] === 'quick') {
                $items[] = $this->resolveQuickGame($playerId, $stub['source_id'], $stub['date']);
            } elseif ($stub['source_type'] === 'game') {
                $items[] = $this->resolveGroupGame($playerId, $stub['source_id'], $stub['date']);
            } elseif ($stub['source_type'] === 'league') {
                $items[] = $this->resolveLeagueGame($playerId, $stub['source_id'], $stub['date']);
            } elseif ($stub['source_type'] === 'training') {
                $items[] = $this->resolveTraining($stub['source_id'], $stub['date']);
            } else {
                $items[] = $this->resolvePlayoffGame($playerId, $stub['source_id'], $stub['date']);
            }
        }

        return $items;
    }

    private function resolveQuickGame(int $playerId, int $quickGameId, string $date): array
    {
        $opponentNames = DB::table('quick_game_results')
            ->join('players', 'players.id', '=', 'quick_game_results.player_id')
            ->where('quick_game_results.quick_game_id', $quickGameId)
            ->where('quick_game_results.player_id', '!=', $playerId)
            ->pluck('players.name')
            ->all();
        $myResult = DB::table('quick_game_results')
            ->where('quick_game_id', $quickGameId)
            ->where('player_id', $playerId)
            ->value('place');
        $winnerPlace = DB::table('quick_game_results')
            ->where('quick_game_id', $quickGameId)
            ->orderBy('place')
            ->value('place');
        $scores = DB::table('quick_game_results')
            ->where('quick_game_id', $quickGameId)
            ->orderBy('place')
            ->pluck('score')
            ->all();
        $won = $myResult === 1;
        $scoreStr = count($scores) >= 2 ? implode(' : ', $scores) : null;

        return [
            'type' => 'quick',
            'id' => $quickGameId,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => implode(', ', $opponentNames) ?: '–',
            'result' => $won ? 'wygrana' : 'porażka',
            'score' => $scoreStr,
            'tournament_name' => null,
        ];
    }

    private function resolveGroupGame(int $playerId, int $gameId, string $date): array
    {
        $row = DB::table('games')
            ->join('players as p1', 'p1.id', '=', 'games.player1_id')
            ->join('players as p2', 'p2.id', '=', 'games.player2_id')
            ->leftJoin('tournaments', 'tournaments.id', '=', 'games.tournament_id')
            ->where('games.id', $gameId)
            ->select(
                'games.player1_id',
                'games.player2_id',
                'games.player1_score',
                'games.player2_score',
                'games.winner_id',
                'p1.name as player1_name',
                'p2.name as player2_name',
                'tournaments.name as tournament_name'
            )
            ->first();
        if (! $row) {
            return $this->emptyItem($date);
        }
        $opponentName = (int) $row->player1_id === $playerId ? $row->player2_name : $row->player1_name;
        $won = (int) $row->winner_id === $playerId;
        $score = $row->player1_score !== null && $row->player2_score !== null
            ? $row->player1_score.' : '.$row->player2_score
            : null;

        return [
            'type' => 'group',
            'id' => $gameId,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => $opponentName,
            'result' => $won ? 'wygrana' : 'porażka',
            'score' => $score,
            'tournament_name' => $row->tournament_name,
        ];
    }

    private function resolvePlayoffGame(int $playerId, int $playoffId, string $date): array
    {
        $row = DB::table('playoff_games')
            ->join('players as p1', 'p1.id', '=', 'playoff_games.player1_id')
            ->join('players as p2', 'p2.id', '=', 'playoff_games.player2_id')
            ->leftJoin('tournaments', 'tournaments.id', '=', 'playoff_games.tournament_id')
            ->where('playoff_games.id', $playoffId)
            ->select(
                'playoff_games.player1_id',
                'playoff_games.player2_id',
                'playoff_games.player1_score',
                'playoff_games.player2_score',
                'playoff_games.winner_id',
                'p1.name as player1_name',
                'p2.name as player2_name',
                'tournaments.name as tournament_name'
            )
            ->first();
        if (! $row) {
            return $this->emptyItem($date);
        }
        $opponentName = (int) $row->player1_id === $playerId ? $row->player2_name : $row->player1_name;
        $won = (int) $row->winner_id === $playerId;
        $score = $row->player1_score !== null && $row->player2_score !== null
            ? $row->player1_score.' : '.$row->player2_score
            : null;

        return [
            'type' => 'playoff',
            'id' => $playoffId,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => $opponentName,
            'result' => $won ? 'wygrana' : 'porażka',
            'score' => $score,
            'tournament_name' => $row->tournament_name,
        ];
    }

    private function resolveLeagueGame(int $playerId, int $leagueGameId, string $date): array
    {
        $row = DB::table('league_games')
            ->join('players as p1', 'p1.id', '=', 'league_games.player1_id')
            ->join('players as p2', 'p2.id', '=', 'league_games.player2_id')
            ->leftJoin('league_seasons', 'league_seasons.id', '=', 'league_games.league_season_id')
            ->leftJoin('leagues', 'leagues.id', '=', 'league_seasons.league_id')
            ->where('league_games.id', $leagueGameId)
            ->select(
                'league_games.player1_id',
                'league_games.player2_id',
                'league_games.player1_score',
                'league_games.player2_score',
                'league_games.winner_id',
                'p1.name as player1_name',
                'p2.name as player2_name',
                'leagues.name as league_name'
            )
            ->first();
        if (! $row) {
            return $this->emptyItem($date);
        }
        $opponentName = (int) $row->player1_id === $playerId ? $row->player2_name : $row->player1_name;
        $won = (int) $row->winner_id === $playerId;
        $score = $row->player1_score !== null && $row->player2_score !== null
            ? $row->player1_score.' : '.$row->player2_score
            : null;

        return [
            'type' => 'league',
            'id' => $leagueGameId,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => $opponentName,
            'result' => $won ? 'wygrana' : 'porażka',
            'score' => $score,
            'tournament_name' => $row->league_name,
        ];
    }

    private function resolveTraining(int $snapshotId, string $date): array
    {
        return [
            'type' => 'training',
            'id' => null,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => 'Trening',
            'result' => '–',
            'score' => null,
            'tournament_name' => null,
        ];
    }

    private function emptyItem(string $date): array
    {
        return [
            'type' => 'unknown',
            'id' => null,
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => '–',
            'result' => '–',
            'score' => null,
            'tournament_name' => null,
        ];
    }
}
