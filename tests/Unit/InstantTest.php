<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Instant;
use PHPUnit\Framework\TestCase;

class InstantTest extends TestCase
{
    public function test_fills_best_orders_first(): void
    {
        $r = Instant::fill(15, [[1, 10.0, 10, 1], [2, 9.0, 100, 1]]);

        $this->assertSame(15, $r['quantity']);
        $this->assertEqualsWithDelta(10 * 10 + 5 * 9, $r['gross'], 1e-9);
    }

    public function test_skips_own_orders_and_minimum_volumes(): void
    {
        $r = Instant::fill(5, [[1, 12.0, 100, 1], [2, 11.0, 100, 10], [3, 8.0, 3, 1]], [1 => true]);

        // own order 1 skipped, order 2 needs 10 units at once, order 3 takes 3
        $this->assertSame(3, $r['quantity']);
        $this->assertEqualsWithDelta(24.0, $r['gross'], 1e-9);
    }

    public function test_no_buy_orders(): void
    {
        $this->assertSame(['quantity' => 0, 'gross' => 0.0], Instant::fill(5, []));
    }
}
