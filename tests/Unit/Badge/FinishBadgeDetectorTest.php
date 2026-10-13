<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\Finish\FinishBadgeDetector;
use App\Enums\GameKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FinishBadgeDetectorTest extends TestCase
{
    #[Test]
    public function counts_every_closed_leg_from_100_up_as_one_badge(): void
    {
        $game = $this->game(visits: [
            $this->visit(1, 100, closed: true),
            $this->visit(1, 170, closed: true),
            $this->visit(1, 121, closed: true),
            $this->visit(1, 99, closed: true),
            $this->visit(1, 180),
            $this->visit(1, 140, closed: true, bust: true),
            $this->visit(1, 160, closed: true, voided: true),
            $this->visit(2, 150, closed: true),
        ]);

        $events = (new FinishBadgeDetector)->detect($game);
        $byKey = [];
        foreach ($events as $event) {
            $byKey[$event['playerId'].':'.$event['badgeKey']] = $event;
            $this->assertSame(BadgeCategory::Finish->value, $event['category']);
        }

        $this->assertSame(3, $byKey['1:100']['amount']);
        $this->assertSame(1, $byKey['2:100']['amount']);
        $this->assertCount(2, $events);
    }

    #[Test]
    public function ignores_guests_and_games_outside_501_competition(): void
    {
        $detector = new FinishBadgeDetector;

        $guest = $this->game(registered: [1], visits: [
            $this->visit(2, 120, closed: true),
        ]);
        $this->assertSame([], $detector->detect($guest));

        $threeOhOne = $this->game(startingScore: 301, visits: [
            $this->visit(1, 120, closed: true),
        ]);
        $this->assertSame([], $detector->detect($threeOhOne));

        $quick = $this->game(kind: GameKind::QUICK, visits: [
            $this->visit(1, 120, closed: true),
        ]);
        $this->assertSame([], $detector->detect($quick));
    }

    /**
     * @param  list<array{playerId: int, score: int, closedLeg: bool, bust: bool, isVoided: bool}>  $visits
     * @param  list<int>  $registered
     */
    private function game(
        array $visits,
        GameKind $kind = GameKind::GROUP,
        int $startingScore = 501,
        array $registered = [1, 2],
    ): FinishedGame {
        return new FinishedGame(
            kind: $kind,
            sourceId: 9,
            startingScore: $startingScore,
            gameType: 'x01',
            playerIds: [1, 2],
            registeredPlayerIds: $registered,
            visits: $visits,
        );
    }

    /**
     * @return array{playerId: int, score: int, closedLeg: bool, bust: bool, isVoided: bool}
     */
    private function visit(
        int $playerId,
        int $score,
        bool $closed = false,
        bool $bust = false,
        bool $voided = false,
    ): array {
        return [
            'playerId' => $playerId,
            'score' => $score,
            'closedLeg' => $closed,
            'bust' => $bust,
            'isVoided' => $voided,
        ];
    }
}
