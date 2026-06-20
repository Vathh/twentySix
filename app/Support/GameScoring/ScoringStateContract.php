<?php

namespace App\Support\GameScoring;

/**
 * Wspólny kontrakt pól odpowiedzi scoringu (turniej H2H + quick FFA).
 * Pola additive — istniejące `game`, `session`, `players` pozostają bez zmian.
 */
final class ScoringStateContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function enrichH2h(array $payload): array
    {
        $game = $payload['game'] ?? [];
        $visits = $payload['visits'] ?? [];
        $currentLeg = $payload['currentLeg'] ?? null;
        $legNumber = (int) ($currentLeg['legNumber'] ?? 0);

        $playerIds = array_values(array_map(
            static fn (array $player): int => (int) ($player['playerId'] ?? 0),
            $payload['players'] ?? [],
        ));

        $payload['format'] = 'h2h';
        $payload['revision'] = self::revisionForH2h($payload);
        $payload['turn'] = [
            'currentPlayerIndex' => VisitRecorder::currentPlayerIndexFromVisits($visits, $playerIds),
            'legOpenerIndex' => 0,
            'legNumber' => $legNumber,
        ];
        $payload['meta'] = [
            'kind' => self::h2hKind((string) ($game['kind'] ?? 'group')),
            'legsToWin' => (int) ($game['legsToWin'] ?? 2),
            'startingScore' => (int) ($game['startingScore'] ?? 501),
            'gameId' => isset($game['id']) ? (int) $game['id'] : null,
            'lobbyId' => null,
            'tournamentId' => isset($game['tournamentId']) ? (int) $game['tournamentId'] : null,
            'quickGameId' => null,
            'status' => ($game['status'] ?? '') === 'finished' ? 'finished' : 'in_progress',
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function enrichFfa(array $payload): array
    {
        $session = $payload['session'] ?? [];
        $game = $payload['game'] ?? [];
        $visits = $payload['visits'] ?? [];

        $payload['format'] = 'ffa';
        $payload['revision'] = self::revisionForFfa($payload);
        $payload['turn'] = [
            'currentPlayerIndex' => (int) ($session['currentPlayerIndex'] ?? 0),
            'legOpenerIndex' => (int) ($session['legOpenerIndex'] ?? 0),
            'legNumber' => (int) ($session['currentLegNumber'] ?? $payload['currentLeg']['legNumber'] ?? 0),
        ];
        $payload['meta'] = [
            'kind' => 'quick_ffa',
            'legsToWin' => (int) ($session['legsToWin'] ?? $game['legsToWin'] ?? 2),
            'startingScore' => (int) ($session['startingScore'] ?? 501),
            'gameId' => isset($game['id']) ? (int) $game['id'] : null,
            'lobbyId' => isset($session['lobbyId']) ? (int) $session['lobbyId'] : null,
            'tournamentId' => null,
            'quickGameId' => isset($session['quickGameId']) ? (int) $session['quickGameId'] : null,
            'status' => self::ffaStatus($session, $game),
        ];

        if ($session === [] || ($session['currentPlayerIndex'] ?? null) === null) {
            $playerIds = array_values(array_map(
                static fn (array $player): int => (int) ($player['playerId'] ?? 0),
                $payload['players'] ?? [],
            ));
            $payload['turn']['currentPlayerIndex'] = VisitRecorder::currentPlayerIndexFromVisits($visits, $playerIds);
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function revisionForH2h(array $payload): int
    {
        $currentLeg = $payload['currentLeg'] ?? null;
        $game = $payload['game'] ?? [];
        $visits = $payload['visits'] ?? [];

        $rev = (int) ($currentLeg['id'] ?? 0) * 1_000_000;
        $rev += (int) ($game['player1LegsWon'] ?? 0) * 10_000;
        $rev += (int) ($game['player2LegsWon'] ?? 0) * 1_000;
        $rev += count($visits) * 100;

        $last = $visits !== [] ? $visits[array_key_last($visits)] : null;
        if ($last) {
            $rev += ((int) ($last['dartsInVisit'] ?? 0)) * 10;
            $rev += min((int) ($last['score'] ?? 0), 180);
        }

        return $rev;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function revisionForFfa(array $payload): int
    {
        $session = $payload['session'] ?? [];
        $visits = $payload['visits'] ?? [];
        $game = $payload['game'] ?? [];

        $rev = (int) ($session['stateVersion'] ?? 0) * 1_000_000;
        $rev += count($visits) * 1_000;

        $last = $visits !== [] ? $visits[array_key_last($visits)] : null;
        if ($last) {
            $rev += ((int) ($last['dartsInVisit'] ?? 0)) * 10;
            $rev += min((int) ($last['score'] ?? 0), 180);
        }

        $maxLegsWon = 0;
        foreach ($payload['players'] ?? [] as $player) {
            $maxLegsWon = max($maxLegsWon, (int) ($player['legsWon'] ?? 0));
        }
        $rev += $maxLegsWon * 10_000;
        $rev += (int) ($session['legsToWin'] ?? $game['legsToWin'] ?? 0);

        if (self::ffaStatus($session, $game) === 'finished') {
            $rev += 999_999_999;
        }

        return $rev;
    }

    private static function h2hKind(string $kind): string
    {
        return match ($kind) {
            'playoff' => 'tournament_playoff',
            'quick' => 'quick_h2h',
            default => 'tournament_group',
        };
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $game
     */
    private static function ffaStatus(array $session, array $game): string
    {
        if (($session['status'] ?? '') === 'finished' || ($game['status'] ?? '') === 'finished') {
            return 'finished';
        }

        return 'in_progress';
    }
}
