<?php

namespace Tests\Unit\Tournament;

use App\Domain\Tournament\PointSchemeDomain;
use App\Domain\Tournament\PointSchemeRuleDomain;
use App\Enums\PointSchemeFormat;
use App\Enums\TournamentFormat;
use Tests\TestCase;

class PointSchemeDomainTest extends TestCase
{
    public function test_points_for_place_uses_se_column_for_groups_and_single_elim(): void
    {
        $scheme = $this->scheme();

        $this->assertSame(7, $scheme->pointsForPlace(TournamentFormat::SingleElimination, 5));
        $this->assertSame(7, $scheme->pointsForPlace(TournamentFormat::GroupsPlayoff, 5));
        $this->assertSame(8, $scheme->pointsForPlace(TournamentFormat::DoubleElimination, 5));
    }

    private function scheme(): PointSchemeDomain
    {
        return new PointSchemeDomain(
            id: 1,
            name: 'test',
            minPlayers: 9,
            maxPlayers: 16,
            rules: collect([
                new PointSchemeRuleDomain(1, PointSchemeFormat::Se, 5, 8, 7),
                new PointSchemeRuleDomain(2, PointSchemeFormat::De, 5, 6, 8),
                new PointSchemeRuleDomain(3, PointSchemeFormat::De, 7, 8, 7),
            ]),
        );
    }
}
