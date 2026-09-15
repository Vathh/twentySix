<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\SeasonPointTable;
use App\Enums\PointSchemeFormat;
use Tests\TestCase;

class SeasonPointTableTest extends TestCase
{
    public function test_se_eight_player_podium_and_quarter(): void
    {
        $this->assertSame(13, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 1));
        $this->assertSame(10, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 2));
        $this->assertSame(8, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 3));
        $this->assertSame(6, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 4));
        $this->assertSame(5, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 5));
        $this->assertSame(5, SeasonPointTable::pointsFor(4, PointSchemeFormat::Se, 8));
    }

    public function test_de_eight_player_splits_places_5_through_8(): void
    {
        $this->assertSame(13, SeasonPointTable::pointsFor(4, PointSchemeFormat::De, 1));
        $this->assertSame(5, SeasonPointTable::pointsFor(4, PointSchemeFormat::De, 5));
        $this->assertSame(5, SeasonPointTable::pointsFor(4, PointSchemeFormat::De, 6));
        $this->assertSame(3, SeasonPointTable::pointsFor(4, PointSchemeFormat::De, 7));
        $this->assertSame(3, SeasonPointTable::pointsFor(4, PointSchemeFormat::De, 8));
    }

    public function test_se_place_5_uses_quarter_bucket_not_de_5_6(): void
    {
        $this->assertSame(7, SeasonPointTable::pointsFor(9, PointSchemeFormat::Se, 5));
        $this->assertSame(8, SeasonPointTable::pointsFor(9, PointSchemeFormat::De, 5));
        $this->assertSame(7, SeasonPointTable::pointsFor(9, PointSchemeFormat::De, 7));
    }

    public function test_groups_32_overall_17_and_25_share_se_bucket(): void
    {
        $this->assertSame(4, SeasonPointTable::pointsFor(17, PointSchemeFormat::Se, 17));
        $this->assertSame(4, SeasonPointTable::pointsFor(17, PointSchemeFormat::Se, 25));
        $this->assertSame(5, SeasonPointTable::pointsFor(17, PointSchemeFormat::De, 17));
        $this->assertSame(3, SeasonPointTable::pointsFor(17, PointSchemeFormat::De, 25));
    }
}
