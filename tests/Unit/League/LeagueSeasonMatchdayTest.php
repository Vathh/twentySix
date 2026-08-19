<?php

namespace Tests\Unit\League;

use App\Models\League\LeagueSeasonMatchday;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueSeasonMatchdayTest extends TestCase
{
    #[Test]
    public function only_the_window_containing_today_is_current(): void
    {
        $matchday = new LeagueSeasonMatchday([
            'round_number' => 1,
            'window_start' => '2026-08-19 00:00:00',
            'window_end' => '2026-08-25 23:59:59',
        ]);

        $this->assertTrue($matchday->isCurrent(Carbon::parse('2026-08-19')));
        $this->assertTrue($matchday->isCurrent(Carbon::parse('2026-08-25')));
        $this->assertFalse($matchday->isCurrent(Carbon::parse('2026-08-18')));
        $this->assertFalse($matchday->isCurrent(Carbon::parse('2026-08-26')));
    }

    #[Test]
    public function first_matchday_is_not_current_when_a_later_window_is_live(): void
    {
        $first = new LeagueSeasonMatchday([
            'round_number' => 1,
            'window_start' => '2026-08-19 00:00:00',
            'window_end' => '2026-08-25 23:59:59',
        ]);
        $second = new LeagueSeasonMatchday([
            'round_number' => 2,
            'window_start' => '2026-08-26 00:00:00',
            'window_end' => '2026-09-01 23:59:59',
        ]);
        $today = Carbon::parse('2026-08-27');

        $this->assertFalse($first->isCurrent($today));
        $this->assertTrue($second->isCurrent($today));
    }
}
