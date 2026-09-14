<?php

namespace Tests\Unit\Support;

use App\Support\AverageFormat;
use Tests\TestCase;

class AverageFormatTest extends TestCase
{
    public function test_whole_number_keeps_two_decimals(): void
    {
        $this->assertSame('72.00', AverageFormat::display(72));
        $this->assertSame('72.00', AverageFormat::display(72.0));
        $this->assertSame('9.00', AverageFormat::display('9'));
    }

    public function test_fractional_average_keeps_two_decimals(): void
    {
        $this->assertSame('72.50', AverageFormat::display(72.5));
        $this->assertSame('90.05', AverageFormat::display(90.05));
    }

    public function test_empty_values_use_placeholder(): void
    {
        $this->assertSame('—', AverageFormat::display(null));
        $this->assertSame('–', AverageFormat::display(null, '–'));
        $this->assertSame('—', AverageFormat::display(''));
    }
}
