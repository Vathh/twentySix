<?php

namespace Tests\Unit\League;

use App\Domain\League\LeagueDivisionSnapshot;
use App\Domain\League\LeaguePromotionResolver;
use App\Domain\League\LeagueStandingRow;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeaguePromotionResolverTest extends TestCase
{
    #[Test]
    public function auto_promotes_top_of_lower_and_relegates_bottom_of_higher(): void
    {
        $higher = new LeagueDivisionSnapshot(1, 0, 'A', 4, 0, 0, [10, 11, 12, 13]);
        $lower = new LeagueDivisionSnapshot(2, 1, 'B', 4, 2, 0, [20, 21, 22, 23]);

        $plan = LeaguePromotionResolver::resolve(
            [$higher, $lower],
            [
                1 => $this->places([10, 11, 12, 13]),
                2 => $this->places([20, 21, 22, 23]),
            ],
        );

        $this->assertSame([], $plan['playoffPairings']);
        $this->assertEqualsCanonicalizing([10, 11, 20, 21], $plan['rosterByDivisionId'][1]);
        $this->assertEqualsCanonicalizing([12, 13, 22, 23], $plan['rosterByDivisionId'][2]);
    }

    #[Test]
    public function playoff_pairs_best_challenger_with_weakest_threatened(): void
    {
        $higher = new LeagueDivisionSnapshot(1, 0, 'A', 4, 0, 0, [10, 11, 12, 13]);
        $lower = new LeagueDivisionSnapshot(2, 1, 'B', 4, 1, 1, [20, 21, 22, 23]);

        $plan = LeaguePromotionResolver::resolve(
            [$higher, $lower],
            [
                1 => $this->places([10, 11, 12, 13]),
                2 => $this->places([20, 21, 22, 23]),
            ],
        );

        $this->assertCount(1, $plan['playoffPairings']);
        $pairing = $plan['playoffPairings'][0];
        $this->assertSame(21, $pairing->lowerPlayerId);
        $this->assertSame(12, $pairing->higherPlayerId);
    }

    #[Test]
    public function vacancy_is_filled_from_below_without_cancelling_relegation(): void
    {
        $higher = new LeagueDivisionSnapshot(1, 0, 'A', 4, 0, 0, [10, 11, 12]);
        $lower = new LeagueDivisionSnapshot(2, 1, 'B', 4, 1, 0, [20, 21, 22, 23]);

        $plan = LeaguePromotionResolver::resolve(
            [$higher, $lower],
            [
                1 => $this->places([10, 11, 12]),
                2 => $this->places([20, 21, 22, 23]),
            ],
        );

        $this->assertCount(4, $plan['rosterByDivisionId'][1]);
        $this->assertContains(20, $plan['rosterByDivisionId'][1]);
        $this->assertContains(21, $plan['rosterByDivisionId'][1]);
        $this->assertNotContains(12, $plan['rosterByDivisionId'][1]);
        $this->assertContains(12, $plan['rosterByDivisionId'][2]);
    }

    /**
     * @param  list<int>  $ids
     * @return list<LeagueStandingRow>
     */
    private function places(array $ids): array
    {
        $rows = [];
        foreach (array_values($ids) as $i => $id) {
            $rows[] = new LeagueStandingRow(
                playerId: $id,
                played: 0,
                wins: 0,
                losses: 0,
                unitsFor: 0,
                unitsAgainst: 0,
                unitDiff: 0,
                place: $i + 1,
            );
        }

        return $rows;
    }
}
