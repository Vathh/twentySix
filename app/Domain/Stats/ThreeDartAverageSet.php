<?php

namespace App\Domain\Stats;

/**
 * Średnie meczu i zawodnika policzone wagą lotek, nie średnią ze średnich.
 */
final class ThreeDartAverageSet
{
    public const KIND_GROUP = 'group';

    public const KIND_PLAYOFF = 'playoff';

    public const KIND_LEAGUE = 'league';

    /**
     * @param  array<string, array<int, array<int, array{points: int, darts: int}>>>  $matches
     * @param  array<string, array<int, array{points: int, darts: int}>>  $playersByKind
     */
    private function __construct(
        private array $matches,
        private array $playersByKind,
    ) {}

    public static function empty(): self
    {
        return new self([], []);
    }

    /**
     * @param  iterable<int, object{kind: string, match_id: int|string, player_id: int|string, points: int|string, darts: int|string}>  $rows
     */
    public static function fromRows(iterable $rows): self
    {
        $matches = [];
        $playersByKind = [];

        foreach ($rows as $row) {
            $kind = (string) $row->kind;
            $matchId = (int) $row->match_id;
            $playerId = (int) $row->player_id;
            $points = (int) $row->points;
            $darts = (int) $row->darts;
            if ($kind === '' || $matchId < 1 || $playerId < 1 || $darts <= 0) {
                continue;
            }

            $matches[$kind][$matchId][$playerId] = [
                'points' => $points,
                'darts' => $darts,
            ];
            $playersByKind[$kind][$playerId]['points'] = ($playersByKind[$kind][$playerId]['points'] ?? 0) + $points;
            $playersByKind[$kind][$playerId]['darts'] = ($playersByKind[$kind][$playerId]['darts'] ?? 0) + $darts;
        }

        return new self($matches, $playersByKind);
    }

    public function matchAverage(string $kind, int $matchId, int $playerId): ?float
    {
        $totals = $this->matches[$kind][$matchId][$playerId] ?? null;
        if ($totals === null) {
            return null;
        }

        return ThreeDartAverage::fromTotals($totals['points'], $totals['darts']);
    }

    public function playerAverage(string $kind, int $playerId): ?float
    {
        $totals = $this->playersByKind[$kind][$playerId] ?? null;
        if ($totals === null) {
            return null;
        }

        return ThreeDartAverage::fromTotals($totals['points'], $totals['darts']);
    }

    /**
     * @param  list<string>  $kinds
     */
    public function playerAverageAcross(array $kinds, int $playerId): ?float
    {
        $points = 0;
        $darts = 0;
        foreach ($kinds as $kind) {
            $totals = $this->playersByKind[$kind][$playerId] ?? null;
            if ($totals === null) {
                continue;
            }
            $points += $totals['points'];
            $darts += $totals['darts'];
        }

        return ThreeDartAverage::fromTotals($points, $darts);
    }

    public function groupMatchAverage(int $matchId, int $playerId): ?float
    {
        return $this->matchAverage(self::KIND_GROUP, $matchId, $playerId);
    }

    public function groupPlayerAverage(int $playerId): ?float
    {
        return $this->playerAverage(self::KIND_GROUP, $playerId);
    }

    public function playoffMatchAverage(int $matchId, int $playerId): ?float
    {
        return $this->matchAverage(self::KIND_PLAYOFF, $matchId, $playerId);
    }

    public function leagueMatchAverage(int $matchId, int $playerId): ?float
    {
        return $this->matchAverage(self::KIND_LEAGUE, $matchId, $playerId);
    }

    public function leaguePlayerAverage(int $playerId): ?float
    {
        return $this->playerAverage(self::KIND_LEAGUE, $playerId);
    }

    public function tournamentPlayerAverage(int $playerId): ?float
    {
        return $this->playerAverageAcross([self::KIND_GROUP, self::KIND_PLAYOFF], $playerId);
    }
}
