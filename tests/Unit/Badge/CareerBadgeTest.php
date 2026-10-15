<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\Career\CareerCatalog;
use App\Domain\Badge\Career\CareerLevelPolicy;
use App\Domain\Badge\Career\CareerTournamentAward;
use App\Domain\Badge\Career\WinStreakCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CareerBadgeTest extends TestCase
{
    #[Test]
    public function streak_keeps_the_record_when_a_later_loss_resets_the_current_run(): void
    {
        $first = WinStreakCalculator::fromWins([true, true, true, false, true, true]);
        $this->assertSame(3, $first['best']);
        $this->assertSame(2, $first['current']);

        $afterAnotherLoss = WinStreakCalculator::fromWins([true, true, true, false, false]);
        $this->assertSame(3, $afterAnotherLoss['best']);
        $this->assertSame(0, $afterAnotherLoss['current']);
    }

    #[Test]
    public function correcting_a_middle_win_into_a_loss_lowers_the_record(): void
    {
        $before = WinStreakCalculator::fromWins([true, true, true]);
        $after = WinStreakCalculator::fromWins([true, false, true]);

        $this->assertSame(3, $before['best']);
        $this->assertSame(1, $after['best']);
        $this->assertSame(1, $after['current']);
    }

    #[Test]
    public function place_one_two_and_three_award_podium_and_only_first_awards_a_title(): void
    {
        $this->assertSame(
            [CareerCatalog::APPEARANCES, CareerCatalog::PODIUM, CareerCatalog::TITLES],
            CareerTournamentAward::keysForPlace(1),
        );
        $this->assertSame(
            [CareerCatalog::APPEARANCES, CareerCatalog::PODIUM],
            CareerTournamentAward::keysForPlace(2),
        );
        $this->assertSame(
            [CareerCatalog::APPEARANCES, CareerCatalog::PODIUM],
            CareerTournamentAward::keysForPlace(3),
        );
        $this->assertSame([CareerCatalog::APPEARANCES], CareerTournamentAward::keysForPlace(4));
        $this->assertSame([CareerCatalog::APPEARANCES], CareerTournamentAward::keysForPlace(null));
    }

    #[Test]
    public function thresholds_match_the_four_career_badges(): void
    {
        $this->assertSame('locked', CareerLevelPolicy::nameForHits(CareerCatalog::PODIUM, 4));
        $this->assertSame('iron', CareerLevelPolicy::nameForHits(CareerCatalog::PODIUM, 5));
        $this->assertSame('diamond', CareerLevelPolicy::nameForHits(CareerCatalog::PODIUM, 500));

        $this->assertSame('iron', CareerLevelPolicy::nameForHits(CareerCatalog::WIN_STREAK, 3));
        $this->assertSame('gold', CareerLevelPolicy::nameForHits(CareerCatalog::WIN_STREAK, 13));
        $this->assertSame('diamond', CareerLevelPolicy::nameForHits(CareerCatalog::WIN_STREAK, 50));

        $this->assertSame('bronze', CareerLevelPolicy::nameForHits(CareerCatalog::APPEARANCES, 25));
        $this->assertSame('platinum', CareerLevelPolicy::nameForHits(CareerCatalog::APPEARANCES, 250));

        $this->assertSame('gold', CareerLevelPolicy::nameForHits(CareerCatalog::TITLES, 50));
        $this->assertSame('diamond', CareerLevelPolicy::nameForHits(CareerCatalog::TITLES, 250));
        $this->assertSame('Podium', CareerCatalog::label(CareerCatalog::PODIUM));
        $this->assertSame('Turniejowiec', CareerCatalog::label(CareerCatalog::APPEARANCES));
    }
}
