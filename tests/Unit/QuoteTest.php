<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Quote;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    private const ITEM = ['type_id' => 34, 'name' => 'Tritanium', 'quantity' => 1000];

    private static function book(array $sell, array $buy = []): array
    {
        return ['sell' => $sell, 'buy' => $buy];
    }

    public function test_undercuts_lowest_sell_by_one_step(): void
    {
        $rows = Quote::rows([self::ITEM], [34 => self::book([[1, 5.0], [2, 5.5]], [[3, 4.0]])],
            [34 => self::book([[10, 4.5]], [[11, 4.2]])], [], []);
        $r = $rows[0];

        $this->assertSame(4.99, $r['price']);
        $this->assertSame(5.0, $r['station_sell']);
        $this->assertSame(4.0, $r['station_buy']);
        $this->assertSame(4.5, $r['jita_sell']);
        $this->assertSame(4.2, $r['jita_buy']);
        $this->assertEqualsWithDelta(4990.0, $r['value'], 1e-9);
        $this->assertEqualsWithDelta((4.99 - 4.5) / 4.5 * 100, $r['vs_jita_pct'], 1e-9);
        $this->assertFalse($r['flags']['no_offer']);
        $this->assertFalse($r['flags']['buy_above']);
    }

    public function test_own_orders_are_skipped_and_marked(): void
    {
        $rows = Quote::rows([self::ITEM], [34 => self::book([[1, 4.0], [2, 5.0]])], [34 => self::book([[10, 4.5]])],
            [1 => true], [34 => true]);

        $this->assertSame(4.99, $rows[0]['price']);
        $this->assertTrue($rows[0]['flags']['own_order']);
    }

    public function test_no_offer_uses_jita_sell(): void
    {
        $rows = Quote::rows([self::ITEM], [34 => self::book([[1, 4.0]])], [34 => self::book([[10, 4.5]])], [1 => true], []);

        $this->assertSame(4.5, $rows[0]['price']);
        $this->assertTrue($rows[0]['flags']['no_offer']);
        $this->assertSame(0.0, $rows[0]['vs_jita_pct']);
    }

    public function test_buy_order_above_price_is_marked(): void
    {
        $rows = Quote::rows([self::ITEM], [34 => self::book([[1, 5.0]], [[2, 4.99]])], [34 => self::book([[10, 4.5]])], [], []);

        $this->assertTrue($rows[0]['flags']['buy_above']);
    }

    public function test_unreadable_market(): void
    {
        $rows = Quote::rows([self::ITEM], [34 => null], [34 => self::book([[10, 4.5]])], [], []);

        $this->assertTrue($rows[0]['flags']['no_data']);
        $this->assertFalse($rows[0]['flags']['no_offer']);
        $this->assertSame(4.5, $rows[0]['price']);
    }

    public function test_import_text(): void
    {
        $rows = Quote::rows([self::ITEM, ['type_id' => 35, 'name' => 'Pyerite', 'quantity' => 5]],
            [34 => self::book([[1, 1234000.0]]), 35 => self::book([])], [], [], []);

        $this->assertSame("Tritanium\t1233000\t1000", Quote::importText($rows));
    }
}
