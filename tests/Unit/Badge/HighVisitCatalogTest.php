<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\HighVisit\HighVisitCatalog;
use App\Domain\Badge\HighVisit\HighVisitLevelPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HighVisitCatalogTest extends TestCase
{
    #[Test]
    public function score_falls_into_one_bucket(): void
    {
        $this->assertNull(HighVisitCatalog::keyForScore(99));
        $this->assertSame('100', HighVisitCatalog::keyForScore(100));
        $this->assertSame('100', HighVisitCatalog::keyForScore(139));
        $this->assertSame('140', HighVisitCatalog::keyForScore(140));
        $this->assertSame('140', HighVisitCatalog::keyForScore(179));
        $this->assertSame('180', HighVisitCatalog::keyForScore(180));
        $this->assertNull(HighVisitCatalog::keyForScore(181));
        $this->assertSame('180', HighVisitCatalog::label('180'));
        $this->assertSame('100+', HighVisitCatalog::label('100'));
    }

    #[Test]
    public function thresholds_step_per_track(): void
    {
        $this->assertSame(0, HighVisitLevelPolicy::levelForHits('100', 0));
        $this->assertSame('locked', HighVisitLevelPolicy::nameForHits('100', 4));
        $this->assertSame(5, HighVisitLevelPolicy::nextThreshold('100', 0));
        $this->assertSame('iron', HighVisitLevelPolicy::nextLevelName('100', 0));

        $this->assertSame(5, HighVisitLevelPolicy::levelForHits('100', 9));
        $this->assertSame('iron', HighVisitLevelPolicy::nameForHits('100', 9));
        $this->assertSame(25, HighVisitLevelPolicy::nextThreshold('100', 9));

        $this->assertSame(25, HighVisitLevelPolicy::levelForHits('100', 25));
        $this->assertSame('bronze', HighVisitLevelPolicy::nameForHits('100', 25));
        $this->assertSame('gold', HighVisitLevelPolicy::nameForHits('100', 100));
        $this->assertSame('platinum', HighVisitLevelPolicy::nameForHits('100', 500));
        $this->assertSame('diamond', HighVisitLevelPolicy::nameForHits('100', 1500));
        $this->assertNull(HighVisitLevelPolicy::nextThreshold('100', 1500));

        $this->assertSame('locked', HighVisitLevelPolicy::nameForHits('140', 2));
        $this->assertSame('iron', HighVisitLevelPolicy::nameForHits('140', 3));
        $this->assertSame('bronze', HighVisitLevelPolicy::nameForHits('140', 10));
        $this->assertSame('gold', HighVisitLevelPolicy::nameForHits('140', 50));
        $this->assertSame('platinum', HighVisitLevelPolicy::nameForHits('140', 250));
        $this->assertSame('diamond', HighVisitLevelPolicy::nameForHits('140', 1000));

        $this->assertSame('iron', HighVisitLevelPolicy::nameForHits('180', 1));
        $this->assertSame('bronze', HighVisitLevelPolicy::nameForHits('180', 5));
        $this->assertSame('gold', HighVisitLevelPolicy::nameForHits('180', 25));
        $this->assertSame('platinum', HighVisitLevelPolicy::nameForHits('180', 100));
        $this->assertSame('diamond', HighVisitLevelPolicy::nameForHits('180', 500));
        $this->assertSame('Diament', HighVisitLevelPolicy::label('diamond'));
        $this->assertSame(0, HighVisitLevelPolicy::numberForName('locked'));
        $this->assertSame(1, HighVisitLevelPolicy::numberForName('iron'));
        $this->assertSame(2, HighVisitLevelPolicy::numberForName('bronze'));
        $this->assertSame(5, HighVisitLevelPolicy::numberForName('diamond'));
        $this->assertSame('images/badges/visits/180/iron.png', HighVisitCatalog::imagePath('180', 'locked'));

        $this->assertSame(['percent' => 0, 'label' => '0/1'], HighVisitLevelPolicy::progress('180', 0));
        $this->assertSame(['percent' => 40, 'label' => '2/5'], HighVisitLevelPolicy::progress('180', 2));
        $this->assertSame(['percent' => 32, 'label' => '32/100'], HighVisitLevelPolicy::progress('100', 32));
        $this->assertSame(['percent' => 100, 'label' => '500'], HighVisitLevelPolicy::progress('180', 500));
        $this->assertSame(['percent' => 100, 'label' => '1600'], HighVisitLevelPolicy::progress('100', 1600));
    }
}
