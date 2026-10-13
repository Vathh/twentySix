<?php

namespace Tests\Unit\Badge;

use App\Support\Badge\BadgeArtworkFrame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BadgeArtworkFrameTest extends TestCase
{
    #[Test]
    public function opaque_artwork_fills_the_same_share_regardless_of_canvas_padding(): void
    {
        $iron = BadgeArtworkFrame::measure($this->png('180', 'iron'));
        $bronze = BadgeArtworkFrame::measure($this->png('180', 'bronze'));
        $diamond = BadgeArtworkFrame::measure($this->png('180', 'diamond'));

        $this->assertEqualsWithDelta(92.0, $this->longSide($iron, 141, 158, 128, 147), 1.5);
        $this->assertEqualsWithDelta(92.0, $this->longSide($bronze, 190, 190, 122, 148), 1.5);
        $this->assertEqualsWithDelta(92.0, $this->longSide($diamond, 190, 190, 165, 153), 1.5);
        $this->assertGreaterThan($iron['width'], $bronze['width']);
    }

    private function png(string $key, string $level): string
    {
        return dirname(__DIR__, 3)."/public/images/badges/visits/{$key}/{$level}.png";
    }

    /**
     * @param  array{width: float, height: float, left: float, top: float}  $placement
     */
    private function longSide(array $placement, int $canvasW, int $canvasH, int $contentW, int $contentH): float
    {
        $placementSide = $contentH >= $contentW ? $placement['height'] : $placement['width'];
        $canvasSide = $contentH >= $contentW ? $canvasH : $canvasW;

        return max($contentW, $contentH) / $canvasSide * $placementSide;
    }
}
