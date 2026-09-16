<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\Checkout\CheckoutCatalog;
use App\Domain\Badge\Checkout\CheckoutLevelPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CheckoutCatalogTest extends TestCase
{
    #[Test]
    public function catalog_has_64_legal_checkouts_100_to_170(): void
    {
        $all = CheckoutCatalog::all();

        $this->assertCount(64, $all);
        $this->assertSame(64, CheckoutCatalog::count());
        $this->assertContains(100, $all);
        $this->assertContains(121, $all);
        $this->assertContains(170, $all);
        $this->assertNotContains(40, $all);
        $this->assertNotContains(159, $all);
        $this->assertNotContains(162, $all);
        $this->assertNotContains(163, $all);
        $this->assertNotContains(165, $all);
        $this->assertNotContains(166, $all);
        $this->assertNotContains(168, $all);
        $this->assertNotContains(169, $all);
        $this->assertTrue(CheckoutCatalog::contains(121));
        $this->assertFalse(CheckoutCatalog::contains(40));
        $this->assertFalse(CheckoutCatalog::contains(159));
    }

    #[Test]
    public function level_is_highest_threshold_at_or_below_hits(): void
    {
        $this->assertSame(0, CheckoutLevelPolicy::levelForHits(0));
        $this->assertSame(1, CheckoutLevelPolicy::levelForHits(1));
        $this->assertSame(1, CheckoutLevelPolicy::levelForHits(2));
        $this->assertSame(3, CheckoutLevelPolicy::levelForHits(3));
        $this->assertSame(3, CheckoutLevelPolicy::levelForHits(4));
        $this->assertSame(5, CheckoutLevelPolicy::levelForHits(5));
        $this->assertSame(5, CheckoutLevelPolicy::levelForHits(6));
        $this->assertSame(7, CheckoutLevelPolicy::levelForHits(7));
        $this->assertSame(7, CheckoutLevelPolicy::levelForHits(9));
        $this->assertSame(10, CheckoutLevelPolicy::levelForHits(10));
        $this->assertSame(10, CheckoutLevelPolicy::levelForHits(15));
        $this->assertSame('bright', CheckoutLevelPolicy::nameForHits(7));
        $this->assertSame('locked', CheckoutLevelPolicy::nameForHits(0));
    }
}
