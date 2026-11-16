<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Ticks;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicksTest extends TestCase
{
    public static function belowCases(): array
    {
        return [
            'cents below 100' => [12.34, 12.33],
            'right below 100' => [100.0, 99.99],
            'tenths' => [123.4, 123.3],
            'right below 1,000' => [1000.0, 999.9],
            'whole ISK' => [1234.0, 1233.0],
            'right below 10,000' => [10000.0, 9999.0],
            'millions' => [1234000.0, 1233000.0],
            'right below a million' => [1000000.0, 999900.0],
            'billions' => [1234000000.0, 1233000000.0],
            'off-step price' => [1234.56, 1234.0],
            'two cents' => [0.02, 0.01],
        ];
    }

    #[DataProvider('belowCases')]
    public function test_below(float $price, float $expected): void
    {
        $this->assertSame($expected, Ticks::below($price));
    }

    public function test_nothing_below_one_cent(): void
    {
        $this->assertNull(Ticks::below(0.01));
        $this->assertNull(Ticks::roundDown(0.004));
    }

    public function test_round_down_and_validity(): void
    {
        $this->assertSame(1234.0, Ticks::roundDown(1234.56));
        $this->assertSame(12.34, Ticks::roundDown(12.34));
        $this->assertTrue(Ticks::isValid(1233000.0));
        $this->assertFalse(Ticks::isValid(1234500.0));
        $this->assertTrue(Ticks::isValid(99.99));
    }

    public function test_format_for_import(): void
    {
        $this->assertSame('1233000', Ticks::format(1233000.0));
        $this->assertSame('999.9', Ticks::format(999.9));
        $this->assertSame('12.3', Ticks::format(12.30));
        $this->assertSame('0.01', Ticks::format(0.01));
    }
}
