<?php

namespace Glubschi\Seat\QuickSell\Tests\Feature;

use Glubschi\Seat\QuickSell\Markets\Books;
use Glubschi\Seat\QuickSell\Markets\Market;
use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Glubschi\Seat\QuickSell\Models\MarketFee;
use Glubschi\Seat\QuickSell\Pricing\Fees;
use Glubschi\Seat\QuickSell\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** The fees table (migration and model) and reading books from the cache. */
class StorageTest extends TestCase
{
    use RefreshDatabase;

    private const BOOK = ['sell' => [[1, 5.0, 10, 1]], 'buy' => []];

    public function test_saved_fees_return_only_entered_values(): void
    {
        MarketFee::create(['user_id' => 7, 'location_id' => 1035466617946, 'broker_pct' => 1.25, 'scc_pct' => null, 'safety_pct' => null]);

        $this->assertSame(['broker_pct' => 1.25], MarketFee::savedFor(7, 1035466617946));
        $this->assertSame([], MarketFee::savedFor(8, 1035466617946));
    }

    public function test_remember_keeps_the_last_values(): void
    {
        MarketFee::remember(7, 60003760, Fees::from(false, ['sales_tax_pct' => 3.375, 'broker_pct' => 1.5]));
        MarketFee::remember(7, 60003760, Fees::from(false, ['sales_tax_pct' => 3.375, 'broker_pct' => 1.2, 'reprocess_tax_pct' => 2]));

        $saved = MarketFee::savedFor(7, 60003760);
        $this->assertSame(1, MarketFee::count());
        $this->assertSame(3.375, $saved['sales_tax_pct']);
        $this->assertSame(1.2, $saved['broker_pct']);
        $this->assertSame(2.0, $saved['reprocess_tax_pct']);
        $this->assertSame(50.0, $saved['reprocess_scrap_pct']);
    }

    public function test_station_books_are_complete_only_with_jita(): void
    {
        $market = new Market('station', 60008494, 'Amarr VIII (Oris) - Emperor Family Academy', 30002187, 10000043);
        OrderBooks::putStation(60008494, 34, self::BOOK, 60);

        $first = Books::cached($market, [34]);
        $this->assertFalse($first['complete']);
        $this->assertSame(self::BOOK, $first['market'][34]);

        OrderBooks::putStation(Market::JITA_STATION, 34, self::BOOK, 60);
        $this->assertTrue(Books::cached($market, [34])['complete']);
    }

    public function test_jita_needs_only_the_items(): void
    {
        $market = new Market('station', 60008494, 'Amarr VIII (Oris) - Emperor Family Academy', 30002187, 10000043);
        OrderBooks::putStation(60008494, 34, self::BOOK, 60);
        OrderBooks::putStation(60008494, 3687, self::BOOK, 60);
        OrderBooks::putStation(Market::JITA_STATION, 3687, self::BOOK, 60);

        // item 3687 and its material 34: complete without a Jita book for the material
        $books = Books::cached($market, [3687, 34], [3687]);
        $this->assertTrue($books['complete']);
        $this->assertSame([3687], array_keys($books['jita']));
    }

    public function test_structure_books(): void
    {
        $market = new Market('structure', 1035466617946, 'Some Keepstar', null, null);
        OrderBooks::putStation(Market::JITA_STATION, 34, self::BOOK, 60);
        OrderBooks::putStation(Market::JITA_STATION, 35, self::BOOK, 60);

        $missing = Books::cached($market, [34, 35]);
        $this->assertFalse($missing['complete']);
        $this->assertSame([34 => null, 35 => null], $missing['market']);

        OrderBooks::markDenied(1035466617946);
        $this->assertTrue(Books::cached($market, [34, 35])['complete']);

        OrderBooks::putStructure(1035466617946, [34 => self::BOOK], 60);
        $cached = Books::cached($market, [34, 35]);
        $this->assertTrue($cached['complete']);
        $this->assertFalse(OrderBooks::isDenied(1035466617946));
        $this->assertSame(['sell' => [], 'buy' => []], $cached['market'][35]);
    }
}
