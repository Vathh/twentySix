<?php

namespace Tests\Unit\Badge;

use App\Domain\Badge\Qf\QfCatalog;
use App\Domain\Badge\Qf\QfLevelPolicy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QfCatalogTest extends TestCase
{
    #[Test]
    public function only_nine_through_nineteen_darts_get_a_key(): void
    {
        $this->assertNull(QfCatalog::keyForDarts(8));
        $this->assertNull(QfCatalog::keyForDarts(20));
        $this->assertSame('9', QfCatalog::keyForDarts(9));
        $this->assertSame('12', QfCatalog::keyForDarts(12));
        $this->assertSame('16', QfCatalog::keyForDarts(16));
        $this->assertSame('19', QfCatalog::keyForDarts(19));
        $this->assertSame('images/badges/qf/9/gold.png', QfCatalog::imagePath('9', 'gold'));
    }

    #[Test]
    public function thresholds_follow_the_dart_band(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], QfLevelPolicy::thresholds('9'));
        $this->assertSame([1, 2, 3, 5, 7], QfLevelPolicy::thresholds('12'));
        $this->assertSame([1, 2, 3, 5, 7], QfLevelPolicy::thresholds('10'));
        $this->assertSame([1, 2, 5, 10, 25], QfLevelPolicy::thresholds('15'));
        $this->assertSame([1, 2, 5, 10, 25], QfLevelPolicy::thresholds('13'));
        $this->assertSame([1, 3, 10, 25, 50], QfLevelPolicy::thresholds('16'));
        $this->assertSame([1, 3, 10, 25, 50], QfLevelPolicy::thresholds('19'));

        $this->assertSame('iron', QfLevelPolicy::nameForHits('9', 1));
        $this->assertSame('bronze', QfLevelPolicy::nameForHits('9', 2));
        $this->assertSame('diamond', QfLevelPolicy::nameForHits('9', 5));
        $this->assertSame('iron', QfLevelPolicy::nameForHits('19', 2));
        $this->assertSame('bronze', QfLevelPolicy::nameForHits('19', 3));
        $this->assertSame('gold', QfLevelPolicy::nameForHits('12', 3));
        $this->assertSame('platinum', QfLevelPolicy::nameForHits('12', 5));
        $this->assertSame(5, QfLevelPolicy::nextThreshold('15', 2));
        $this->assertSame('Złoto', QfLevelPolicy::label(QfLevelPolicy::nextLevelName('15', 2)));
    }
}
