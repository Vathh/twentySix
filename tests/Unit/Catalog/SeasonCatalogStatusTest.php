<?php

namespace Tests\Unit\Catalog;

use App\Support\Catalog\SeasonCatalogStatus;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class SeasonCatalogStatusTest extends TestCase
{
    public function test_phase_follows_start_and_end_against_today(): void
    {
        $today = Carbon::parse('2026-10-07');

        $this->assertSame(
            SeasonCatalogStatus::PLANNED,
            SeasonCatalogStatus::phase(Carbon::parse('2026-10-08'), Carbon::parse('2026-12-01'), $today),
        );
        $this->assertSame(
            SeasonCatalogStatus::LIVE,
            SeasonCatalogStatus::phase(Carbon::parse('2026-10-07'), Carbon::parse('2026-10-07'), $today),
        );
        $this->assertSame(
            SeasonCatalogStatus::LIVE,
            SeasonCatalogStatus::phase(Carbon::parse('2026-09-01'), null, $today),
        );
        $this->assertSame(
            SeasonCatalogStatus::FINISHED,
            SeasonCatalogStatus::phase(Carbon::parse('2026-01-01'), Carbon::parse('2026-10-06'), $today),
        );
        $this->assertSame(
            SeasonCatalogStatus::FINISHED,
            SeasonCatalogStatus::phase(Carbon::parse('2026-12-01'), Carbon::parse('2026-10-06'), $today),
        );
        $this->assertNull(SeasonCatalogStatus::phase(null, null, $today));
        $this->assertSame('Zaplanowany', SeasonCatalogStatus::label(SeasonCatalogStatus::PLANNED));
        $this->assertSame('W trakcie', SeasonCatalogStatus::label(SeasonCatalogStatus::LIVE));
        $this->assertSame('Zakończony', SeasonCatalogStatus::label(SeasonCatalogStatus::FINISHED));
    }
}
