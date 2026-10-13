<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\Finish\FinishCatalog;
use App\Domain\Badge\Finish\FinishLevelPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FinishCatalogTest extends TestCase
{
    #[Test]
    public function every_checkout_from_100_up_shares_one_key(): void
    {
        $this->assertNull(FinishCatalog::keyForScore(99));
        $this->assertSame('100', FinishCatalog::keyForScore(100));
        $this->assertSame('100', FinishCatalog::keyForScore(170));
        $this->assertSame('100+', FinishCatalog::label('100'));
        $this->assertSame('images/badges/finish/100/gold.png', FinishCatalog::imagePath('100', 'gold'));
        $this->assertSame('images/badges/finish/100/iron.png', FinishCatalog::imagePath('100', 'locked'));
    }

    #[Test]
    public function thresholds_are_five_twenty_five_hundred_two_fifty_and_a_thousand(): void
    {
        $this->assertSame('locked', FinishLevelPolicy::nameForHits('100', 4));
        $this->assertSame(0, FinishLevelPolicy::numberForName('locked'));
        $this->assertSame('iron', FinishLevelPolicy::nameForHits('100', 5));
        $this->assertSame('bronze', FinishLevelPolicy::nameForHits('100', 25));
        $this->assertSame('gold', FinishLevelPolicy::nameForHits('100', 100));
        $this->assertSame('platinum', FinishLevelPolicy::nameForHits('100', 250));
        $this->assertSame('diamond', FinishLevelPolicy::nameForHits('100', 1000));
        $this->assertSame(5, FinishLevelPolicy::nextThreshold('100', 4));
        $this->assertSame('Żelazo', FinishLevelPolicy::label(FinishLevelPolicy::nextLevelName('100', 4)));
        $this->assertSame(['percent' => 80, 'label' => '4/5'], FinishLevelPolicy::progress('100', 4));
        $this->assertSame(['percent' => 100, 'label' => '1000'], FinishLevelPolicy::progress('100', 1000));
    }
}
