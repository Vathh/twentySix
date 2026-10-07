<?php

namespace Tests\Unit;

use App\Support\Text\PolishFold;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PolishFoldTest extends TestCase
{
    public function test_folds_polish_letters_both_ways(): void
    {
        $this->assertSame('suwalki', PolishFold::fold('Suwałki'));
        $this->assertSame('suwalki', PolishFold::fold('suwalki'));
        $this->assertSame('suwalki', PolishFold::fold('SUWAŁKI'));
        $this->assertSame('lodz', PolishFold::fold('Łódź'));
        $this->assertSame('krakow', PolishFold::fold('Kraków'));
        $this->assertSame('poznan', PolishFold::fold('Poznań'));
    }

    public function test_like_pattern_escapes_wildcards(): void
    {
        $this->assertNull(PolishFold::likePattern('   '));
        $this->assertSame('%suwalki%', PolishFold::likePattern(' Suwałki '));
        $this->assertSame('%100\\%%', PolishFold::likePattern('100%'));
        $this->assertSame('%a\\_b%', PolishFold::likePattern('a_b'));
    }

    public function test_match_summary_uses_polish_plural(): void
    {
        $this->assertNull(PolishFold::matchSummary(0, 'suwałki', 'organizacja', 'organizacje', 'organizacji'));
        $this->assertNull(PolishFold::matchSummary(2, null, 'organizacja', 'organizacje', 'organizacji'));
        $this->assertSame(
            '1 organizacja dla „suwałki”',
            PolishFold::matchSummary(1, 'suwałki', 'organizacja', 'organizacje', 'organizacji'),
        );
        $this->assertSame(
            '4 organizacje dla „suwałki”',
            PolishFold::matchSummary(4, 'suwałki', 'organizacja', 'organizacje', 'organizacji'),
        );
        $this->assertSame(
            '12 organizacji dla „suwałki”',
            PolishFold::matchSummary(12, 'suwałki', 'organizacja', 'organizacje', 'organizacji'),
        );
    }

    public function test_rejects_unsafe_column(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PolishFold::foldedSql('name; drop');
    }
}
