<?php

namespace App\Services\GameScoring;

use App\Models\Player\Player;
use App\Support\GameScoring\H2hLegVisitRows;

/**
 * JSON szczegółów meczu dla aplikacji — bez modeli Eloquent.
 */
final class GameDetailApiPayload
{
    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    public static function from(array $detail): array
    {
        $player1 = $detail['player1'] ?? null;
        $player2 = $detail['player2'] ?? null;
        $player1Id = (int) ($player1->id ?? 0);
        $player2Id = (int) ($player2->id ?? 0);

        $sets = [];
        foreach ($detail['legsBySet'] ?? [] as $set) {
            $legs = [];
            foreach ($set['legs'] ?? [] as $block) {
                $leg = $block['leg'];
                $winnerId = $leg->winner_id !== null ? (int) $leg->winner_id : null;
                $stats = [];
                foreach ($block['playerStats'] ?? [] as $stat) {
                    $stats[] = [
                        'playerId' => (int) $stat->player_id,
                        'name' => $stat->player->name ?? (string) $stat->player_id,
                        'legAverage' => $stat->leg_average,
                        'firstNineAverage' => $stat->first_nine_average ?? $stat->leg_average,
                        'highestVisit' => $stat->highest_visit,
                        'doubleTracked' => (bool) $stat->double_tracked,
                        'doubleAttempts' => $stat->double_attempts !== null ? (int) $stat->double_attempts : null,
                        'doubleSuccesses' => $stat->double_successes !== null ? (int) $stat->double_successes : null,
                    ];
                }

                $legs[] = [
                    'legNumber' => (int) $leg->leg_number,
                    'legInSetNumber' => (int) ($block['legInSetNumber'] ?? $leg->leg_number),
                    'finished' => $leg->finished_at !== null,
                    'winnerId' => $winnerId,
                    'winnerName' => self::nameForId($winnerId, $player1, $player2),
                    'playerStats' => $stats,
                    'visits' => H2hLegVisitRows::fromVisits($block['visits'] ?? [], $player1Id, $player2Id),
                ];
            }

            $sets[] = [
                'setNumber' => (int) ($set['setNumber'] ?? 1),
                'legs' => $legs,
            ];
        }

        return [
            'kind' => $detail['kind'],
            'gameId' => (int) $detail['gameId'],
            'label' => $detail['label'],
            'subtitle' => $detail['subtitle'],
            'formatLabel' => $detail['formatLabel'],
            'scoreUnit' => $detail['scoreUnit'],
            'leagueMatchdayLabel' => $detail['leagueMatchdayLabel'] ?? null,
            'leagueWalkoverLabel' => $detail['leagueWalkoverLabel'] ?? null,
            'usesSetScore' => (bool) ($detail['usesSetScore'] ?? false),
            'status' => $detail['status'],
            'player1' => self::playerRef($player1),
            'player2' => self::playerRef($player2),
            'player1Score' => (int) $detail['player1Score'],
            'player2Score' => (int) $detail['player2Score'],
            'winnerId' => $detail['winnerId'] !== null ? (int) $detail['winnerId'] : null,
            'players' => array_values($detail['players'] ?? []),
            'sets' => $sets,
        ];
    }

    private static function playerRef(?object $player): ?array
    {
        if ($player === null || ! isset($player->id)) {
            return null;
        }

        $name = (string) ($player->name ?? '—');

        return [
            'id' => (int) $player->id,
            'name' => $name,
            'userId' => $player->user_id !== null ? (int) $player->user_id : null,
            'initials' => method_exists($player, 'initials')
                ? $player->initials()
                : Player::initialsFromName($name),
            'avatarUrl' => method_exists($player, 'avatarUrl') ? $player->avatarUrl() : null,
        ];
    }

    private static function nameForId(?int $playerId, ?object $player1, ?object $player2): ?string
    {
        if ($playerId === null) {
            return null;
        }
        if ($player1 !== null && (int) ($player1->id ?? 0) === $playerId) {
            return (string) ($player1->name ?? '—');
        }
        if ($player2 !== null && (int) ($player2->id ?? 0) === $playerId) {
            return (string) ($player2->name ?? '—');
        }

        return null;
    }
}
