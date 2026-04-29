<?php

namespace App\Repositories\Player;

use Illuminate\Support\Facades\DB;

class PlayerMatchHistoryRepository
{
    private const PER_PAGE = 5;
    private const MAX_STUBS = 500;

    /**
     * Zwraca stronę historii meczów gracza (quick + turniejowe grupy + play-off), posortowaną od najnowszych.
     *
     * @return array{items: array<int, array{type: string, date: string, date_formatted: string, opponents: string, result: string, score: string|null, tournament_name: string|null}>, has_more: bool}
     */
    public function getHistoryPage(int $playerId, int $page): array
    {
        $offset = ($page - 1) * self::PER_PAGE;
        $stubs = $this->fetchAllStubs($playerId);
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
    private function fetchAllStubs(int $playerId): array
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

        $merged = array_merge($quick, $games, $playoff);
        usort($merged, fn ($a, $b) => strcmp($b['date'], $a['date']));
        return array_slice($merged, 0, self::MAX_STUBS);
    }

    /**
     * Dla wycinka stubów pobiera szczegóły (przeciwnicy, wynik, turniej).
     *
     * @param array<int, array{type: string, date: string, source_id: int, source_type: string}> $stubs
     * @return list<array{type: string, date: string, date_formatted: string, opponents: string, result: string, score: string|null, tournament_name: string|null}>
     */
    private function resolveDetails(int $playerId, array $stubs): array
    {
        $items = [];
        foreach ($stubs as $stub) {
            if ($stub['source_type'] === 'quick') {
                $items[] = $this->resolveQuickGame($playerId, $stub['source_id'], $stub['date']);
            } elseif ($stub['source_type'] === 'game') {
                $items[] = $this->resolveGroupGame($playerId, $stub['source_id'], $stub['date']);
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
        if (!$row) {
            return $this->emptyItem($date);
        }
        $opponentName = (int) $row->player1_id === $playerId ? $row->player2_name : $row->player1_name;
        $won = (int) $row->winner_id === $playerId;
        $score = $row->player1_score !== null && $row->player2_score !== null
            ? $row->player1_score . ' : ' . $row->player2_score
            : null;

        return [
            'type' => 'group',
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
        if (!$row) {
            return $this->emptyItem($date);
        }
        $opponentName = (int) $row->player1_id === $playerId ? $row->player2_name : $row->player1_name;
        $won = (int) $row->winner_id === $playerId;
        $score = $row->player1_score !== null && $row->player2_score !== null
            ? $row->player1_score . ' : ' . $row->player2_score
            : null;

        return [
            'type' => 'playoff',
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => $opponentName,
            'result' => $won ? 'wygrana' : 'porażka',
            'score' => $score,
            'tournament_name' => $row->tournament_name,
        ];
    }

    private function emptyItem(string $date): array
    {
        return [
            'type' => 'unknown',
            'date' => $date,
            'date_formatted' => date('d.m.Y H:i', strtotime($date)),
            'opponents' => '–',
            'result' => '–',
            'score' => null,
            'tournament_name' => null,
        ];
    }
}











