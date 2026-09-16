<?php

namespace Tests\Unit\Checkout;

use App\Support\Checkout\CheckoutWheelLevels;
use PHPUnit\Framework\TestCase;

class CheckoutWheelLevelsTest extends TestCase
{
    public function test_thresholds_match_badge_rarity(): void
    {
        $this->assertSame('locked', CheckoutWheelLevels::fromHits(0));
        $this->assertSame('iron', CheckoutWheelLevels::fromHits(1));
        $this->assertSame('iron', CheckoutWheelLevels::fromHits(2));
        $this->assertSame('bronze', CheckoutWheelLevels::fromHits(3));
        $this->assertSame('bronze', CheckoutWheelLevels::fromHits(4));
        $this->assertSame('gold', CheckoutWheelLevels::fromHits(5));
        $this->assertSame('gold', CheckoutWheelLevels::fromHits(6));
        $this->assertSame('bright', CheckoutWheelLevels::fromHits(7));
        $this->assertSame('apex', CheckoutWheelLevels::fromHits(10));
        $this->assertSame('apex', CheckoutWheelLevels::fromHits(15));
        $this->assertSame('apex', CheckoutWheelLevels::fromHits(40));
    }
}
