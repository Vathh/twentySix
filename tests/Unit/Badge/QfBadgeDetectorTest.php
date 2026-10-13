<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\BadgeCategory;
use App\Domain\Badge\FinishedGame;
use App\Domain\Badge\Qf\QfBadgeDetector;
use App\Enums\GameKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QfBadgeDetectorTest extends TestCase
{
    #[Test]
    public function a_nine_dart_leg_counts_only_as_nine(): void
    {
        $game = $this->game(legs: [
            ['playerId' => 1, 'dartsThrown' => 9],
            ['playerId' => 1, 'dartsThrown' => 9],
            ['playerId' => 1, 'dartsThrown' => 12],
            ['playerId' => 1, 'dartsThrown' => 19],
            ['playerId' => 2, 'dartsThrown' => 15],
            ['playerId' => 1, 'dartsThrown' => 8],
            ['playerId' => 1, 'dartsThrown' => 20],
        ]);

        $events = (new QfBadgeDetector)->detect($game);
        $byKey = [];
        foreach ($events as $event) {
            $byKey[$event['playerId'].':'.$event['badgeKey']] = $event;
            $this->assertSame(BadgeCategory::Qf->value, $event['category']);
        }

        $this->assertSame(2, $byKey['1:9']['amount']);
        $this->assertSame(1, $byKey['1:12']['amount']);
        $this->assertSame(1, $byKey['1:19']['amount']);
        $this->assertSame(1, $byKey['2:15']['amount']);
        $this->assertArrayNotHasKey('1:10', $byKey);
        $this->assertArrayNotHasKey('1:8', $byKey);
        $this->assertArrayNotHasKey('1:20', $byKey);
        $this->assertCount(4, $events);
    }

    #[Test]
    public function ignores_guests_and_games_outside_501_competition(): void
    {
        $detector = new QfBadgeDetector;

        $guest = $this->game(registered: [1], legs: [
            ['playerId' => 2, 'dartsThrown' => 9],
        ]);
        $this->assertSame([], $detector->detect($guest));

        $threeOhOne = $this->game(startingScore: 301, legs: [
            ['playerId' => 1, 'dartsThrown' => 9],
        ]);
        $this->assertSame([], $detector->detect($threeOhOne));

        $quick = $this->game(kind: GameKind::QUICK, legs: [
            ['playerId' => 1, 'dartsThrown' => 9],
        ]);
        $this->assertSame([], $detector->detect($quick));
    }

    /**
     * @param  list<array{playerId: int, dartsThrown: int}>  $legs
     * @param  list<int>  $registered
     */
    private function game(
        array $legs,
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
            visits: [],
            legs: $legs,
        );
    }
}
