<?php

namespace Database\Seeders\Support;

use App\Models\Game\Game;
use App\Models\Game\GameLeg;
use App\Models\Game\GameLegPlayerStat;
use App\Models\Game\GameVisit;
use App\Models\PlayoffGame\PlayoffGame;
use Illuminate\Support\Str;

/**
 * Fikcyjne legi, wizyty i statystyki dla zakończonych meczów demo.
 */
class DemoGameScoringFactory
{
    public static function seedForGroupGame(Game $game): void
    {
        if ($game->status->value !== 'finished' || ! $game->winner_id) {
            return;
        }

        $p1 = (int) $game->player1_id;
        $p2 = (int) $game->player2_id;
        $winnerId = (int) $game->winner_id;
        $loserId = $winnerId === $p1 ? $p2 : $p1;
        $p1Legs = (int) $game->player1_score;
        $p2Legs = (int) $game->player2_score;

        $winnerLegCount = $winnerId === $p1 ? $p1Legs : $p2Legs;
        $totalLegs = $p1Legs + $p2Legs;

        for ($legNum = 1; $legNum <= $totalLegs; $legNum++) {
            $legWinner = $legNum <= $winnerLegCount ? $winnerId : $loserId;
            self::seedLeg($game->id, null, $legNum, $p1, $p2, $legWinner, $game->id * 1000 + $legNum);
        }
    }

    public static function seedForPlayoffGame(PlayoffGame $game): void
    {
        if ($game->status->value !== 'finished' || ! $game->winner_id) {
            return;
        }

        $p1 = (int) $game->player1_id;
        $p2 = (int) $game->player2_id;
        $winnerId = (int) $game->winner_id;
        $loserId = $winnerId === $p1 ? $p2 : $p1;
        $winnerLegs = $winnerId === $p1 ? (int) $game->player1_score : (int) $game->player2_score;
        $totalLegs = (int) $game->player1_score + (int) $game->player2_score;

        for ($legNum = 1; $legNum <= max(1, $totalLegs); $legNum++) {
            $legWinner = $legNum <= $winnerLegs ? $winnerId : $loserId;
            self::seedLeg(null, $game->id, $legNum, $p1, $p2, $legWinner, $game->id * 1000 + $legNum);
        }
    }

    private static function seedLeg(
        ?int $gameId,
        ?int $playoffGameId,
        int $legNumber,
        int $player1Id,
        int $player2Id,
        int $legWinnerId,
        int $seed,
    ): void {
        $leg = GameLeg::create([
            'game_id' => $gameId,
            'playoff_game_id' => $playoffGameId,
            'leg_number' => $legNumber,
            'player1_score' => 0,
            'player2_score' => 0,
            'winner_id' => $legWinnerId,
            'started_at' => now()->subHours(3 - $legNumber),
            'finished_at' => now()->subHours(2 - $legNumber),
        ]);

        $hash = crc32((string) $seed);
        $p1Tracked = ($hash % 3) !== 0;
        $p2Tracked = ($hash % 5) !== 0;

        foreach ([$player1Id, $player2Id] as $playerId) {
            $tracked = $playerId === $player1Id ? $p1Tracked : $p2Tracked;
            $visits = self::generateVisits($leg->id, $playerId, $legWinnerId === $playerId, $seed + $playerId);
            $stats = self::statsFromVisits($visits, $tracked, $playerId === $legWinnerId);

            GameLegPlayerStat::create([
                'game_leg_id' => $leg->id,
                'player_id' => $playerId,
                ...$stats,
            ]);
        }

        $p1Pts = GameVisit::query()->where('game_leg_id', $leg->id)->where('player_id', $player1Id)->where('is_voided', false)->sum('score');
        $p2Pts = GameVisit::query()->where('game_leg_id', $leg->id)->where('player_id', $player2Id)->where('is_voided', false)->sum('score');
        $leg->update(['player1_score' => $p1Pts, 'player2_score' => $p2Pts]);
    }

    /**
     * @return list<array{score: int, remaining_before: int, remaining_after: int, darts: int, closed: bool}>
     */
    private static function generateVisits(int $legId, int $playerId, bool $winsLeg, int $seed): array
    {
        $remaining = 501;
        $visitNum = 0;
        $generated = [];
        $h = $seed;

        while ($remaining > 0 && count($generated) < 12) {
            $visitNum++;
            $h = crc32($h.'-'.$visitNum);
            $score = min($remaining, 40 + ($h % 100));
            if ($winsLeg && $remaining <= 100 && $remaining > 0) {
                $score = $remaining;
            }
            $before = $remaining;
            $after = max(0, $remaining - $score);
            $closed = $after === 0;
            $darts = ($h % 3) + 1;

            GameVisit::create([
                'game_leg_id' => $legId,
                'player_id' => $playerId,
                'visit_number' => $visitNum,
                'score' => $score,
                'remaining_before' => $before,
                'remaining_after' => $after,
                'darts_in_visit' => $darts,
                'closed_leg' => $closed,
                'bust' => false,
                'is_voided' => false,
                'client_visit_id' => (string) Str::uuid(),
            ]);

            $generated[] = ['score' => $score, 'remaining_before' => $before, 'remaining_after' => $after, 'darts' => $darts, 'closed' => $closed];
            $remaining = $after;
            if ($closed) {
                break;
            }
        }

        return $generated;
    }

    /**
     * @param  list<array{score: int, remaining_before: int, remaining_after: int, darts: int, closed: bool}>  $visits
     * @return array<string, mixed>
     */
    private static function statsFromVisits(array $visits, bool $doubleTracked, bool $wonLeg): array
    {
        $darts = array_sum(array_column($visits, 'darts'));
        $points = array_sum(array_column($visits, 'score'));
        $legAvg = $darts > 0 ? round(($points / $darts) * 3, 2) : null;
        $firstThree = array_slice($visits, 0, 3);
        $fnDarts = array_sum(array_column($firstThree, 'darts'));
        $fnPts = array_sum(array_column($firstThree, 'score'));
        $fnAvg = $darts < 9
            ? $legAvg
            : (count($firstThree) >= 3 && $fnDarts > 0 ? round(($fnPts / $fnDarts) * 3, 2) : $legAvg);
        $maxVisit = $visits === [] ? null : max(array_column($visits, 'score'));
        $checkout = null;
        $checkoutDart = null;
        foreach ($visits as $v) {
            if ($v['closed']) {
                $checkout = $v['score'];
                $checkoutDart = $v['darts'];
                break;
            }
        }

        $attempts = null;
        $successes = null;
        if ($doubleTracked) {
            $h = crc32('dbl-'.($wonLeg ? '1' : '0').'-'.$darts);
            $attempts = 4 + ($h % 9);
            $successes = max(1, (int) round($attempts * (0.25 + ($h % 30) / 100)));
            $successes = min($successes, $attempts);
        }

        return [
            'leg_average' => $legAvg,
            'first_nine_average' => $fnAvg,
            'highest_visit' => $maxVisit,
            'highest_finish' => $checkout,
            'darts_thrown' => $darts,
            'checkout_dart' => $checkoutDart,
            'double_tracked' => $doubleTracked,
            'double_attempts' => $attempts,
            'double_successes' => $successes,
        ];
    }

    public static function seedTournament(int $tournamentId): void
    {
        Game::query()->where('tournament_id', $tournamentId)->where('status', 'finished')->each(
            fn (Game $g) => self::seedForGroupGame($g)
        );
        PlayoffGame::query()->where('tournament_id', $tournamentId)->where('status', 'finished')->each(
            fn (PlayoffGame $g) => self::seedForPlayoffGame($g)
        );
    }
}
