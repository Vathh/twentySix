<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\HighVisit\HighVisitBadgeDetector;
use App\Enums\GameKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HighVisitBadgeDetectorTest extends TestCase
{
    #[Test]
    public function counts_each_visit_in_one_bucket(): void
    {
        $game = $this->game(visits: [
            $this->visit(1, 180),
            $this->visit(1, 180),
            $this->visit(1, 160),
            $this->visit(1, 110),
            $this->visit(1, 99),
            $this->visit(1, 121, closed: true),
            $this->visit(2, 140),
        ]);

        $events = (new HighVisitBadgeDetector)->detect($game);
        $byKey = [];
        foreach ($events as $event) {
            $byKey[$event['playerId'].':'.$event['badgeKey']] = $event;
            $this->assertSame(BadgeCategory::HighVisit->value, $event['category']);
        }

        $this->assertSame(2, $byKey['1:180']['amount']);
        $this->assertSame(1, $byKey['1:140']['amount']);
        $this->assertSame(2, $byKey['1:100']['amount']);
        $this->assertSame(1, $byKey['2:140']['amount']);
        $this->assertArrayNotHasKey('1:100+', $byKey);
        $this->assertCount(4, $events);
    }

    #[Test]
    public function ignores_bust_voided_guest_and_ineligible_games(): void
    {
        $detector = new HighVisitBadgeDetector;

        $skipped = $this->game(visits: [
            $this->visit(1, 180, bust: true),
            $this->visit(1, 140, voided: true),
            $this->visit(1, 60),
        ]);
        $this->assertSame([], $detector->detect($skipped));

        $guest = $this->game(registered: [1], visits: [
            $this->visit(2, 180),
        ]);
        $this->assertSame([], $detector->detect($guest));

        $threeOhOne = $this->game(startingScore: 301, visits: [
            $this->visit(1, 180),
        ]);
        $this->assertSame([], $detector->detect($threeOhOne));

        $quick = $this->game(kind: GameKind::QUICK, visits: [
            $this->visit(1, 180),
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
    private function visit(int $playerId, int $score, bool $closed = false, bool $bust = false, bool $voided = false): array
    {
        return [
            'playerId' => $playerId,
            'score' => $score,
            'closedLeg' => $closed,
            'bust' => $bust,
            'isVoided' => $voided,
        ];
    }
}
