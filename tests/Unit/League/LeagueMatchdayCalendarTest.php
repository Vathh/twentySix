<?php

namespace Tests\Unit\League;

use App\Domain\League\LeagueMatchdayCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueMatchdayCalendarTest extends TestCase
{
    #[Test]
    public function seven_day_windows_start_on_consecutive_mondays(): void
    {
        $windows = LeagueMatchdayCalendar::windows(Carbon::parse('2026-09-07'), 7, 3);

        $this->assertCount(3, $windows);
        $this->assertSame('2026-09-07 00:00:00', $windows[0]['window_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-13 23:59:59', $windows[0]['window_end']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-14 00:00:00', $windows[1]['window_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-20 23:59:59', $windows[1]['window_end']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-21 00:00:00', $windows[2]['window_start']->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-27 23:59:59', $windows[2]['window_end']->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function equal_span_splits_season_into_even_windows(): void
    {
        $windows = LeagueMatchdayCalendar::equalSpanWindows(
            Carbon::parse('2026-09-01')->startOfDay(),
            Carbon::parse('2026-09-21')->endOfDay(),
            3,
        );

        $this->assertCount(3, $windows);
        $this->assertSame('2026-09-01', $windows[0]['window_start']->format('Y-m-d'));
        $this->assertSame('2026-09-21', $windows[2]['window_end']->format('Y-m-d'));
        $this->assertTrue($windows[0]['window_end']->lt($windows[1]['window_start']));
        $this->assertTrue($windows[1]['window_end']->lt($windows[2]['window_start']));
    }
}
