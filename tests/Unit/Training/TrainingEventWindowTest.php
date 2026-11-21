<?php

namespace Tests\Unit\Training;

use App\Domain\Training\TrainingEventWindow;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class TrainingEventWindowTest extends TestCase
{
    public function test_calendar_week_key(): void
    {
        $at = CarbonImmutable::parse('2026-11-21'); // sobota
        $key = TrainingEventWindow::key(
            TrainingEventWindow::MODE_CALENDAR_WEEK,
            $at,
            CarbonImmutable::parse('2026-11-01'),
            null,
        );
        $this->assertSame('2026-W47', $key);
    }

    public function test_from_start_blocks(): void
    {
        $start = CarbonImmutable::parse('2026-08-25');
        $first = TrainingEventWindow::key(
            TrainingEventWindow::MODE_FROM_START,
            CarbonImmutable::parse('2026-08-28'),
            $start,
            null,
        );
        $second = TrainingEventWindow::key(
            TrainingEventWindow::MODE_FROM_START,
            CarbonImmutable::parse('2026-09-02'),
            $start,
            null,
        );
        $this->assertSame('2026-08-25', $first);
        $this->assertSame('2026-09-01', $second);
    }
}
