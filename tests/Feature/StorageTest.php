<?php

namespace Glubschi\Seat\QuickSell\Tests\Feature;

use Glubschi\Seat\QuickSell\Markets\Books;
use Glubschi\Seat\QuickSell\Markets\Market;
use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use Glubschi\Seat\QuickSell\Models\MarketFee;
use Glubschi\Seat\QuickSell\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** The fees table (migration and model) and reading books from the cache. */
class StorageTest extends TestCase
{
    use RefreshDatabase;

    private const BOOK = ['sell' => [[1, 5.0]], 'buy' => []];

    public function test_saved_fees_return_only_entered_values(): void
    {
        MarketFee::create(['user_id' => 7, 'location_id' => 1035466617946, 'broker_pct' => 1.25, 'scc_pct' => null, 'safety_pct' => null]);

        $this->assertSame(['broker_pct' => 1.25], MarketFee::savedFor(7, 1035466617946));
        $this->assertSame([], MarketFee::savedFor(8, 1035466617946));
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
