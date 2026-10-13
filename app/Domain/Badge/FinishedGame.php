<?php

namespace App\Domain\Badge;

use App\Enums\GameKind;

/**
 * @phpstan-type Visit array{
 *     playerId: int,
 *     score: int,
 *     closedLeg: bool,
 *     bust: bool,
 *     isVoided: bool
 * }
 * @phpstan-type ClosedLeg array{playerId: int, dartsThrown: int}
 */
final class FinishedGame
{
    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $registeredPlayerIds
     * @param  list<Visit>  $visits
     * @param  list<ClosedLeg>  $legs
     */
    public function __construct(
        public GameKind $kind,
        public int $sourceId,
        public int $startingScore,
        public string $gameType,
        public array $playerIds,
        public array $registeredPlayerIds,
        public array $visits,
        public array $legs = [],
    ) {}
}
