<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Carbon\Carbon;
use Glubschi\Seat\QuickSell\Markets\OrderBooks;
use PHPUnit\Framework\TestCase;

class OrderBooksReduceTest extends TestCase
{
    private static function order(int $id, int $location, float $price, bool $buy, int $type = 34): object
    {
        return (object) ['order_id' => $id, 'type_id' => $type, 'location_id' => $location, 'price' => $price, 'is_buy_order' => $buy];
    }

    public function test_groups_sorts_and_filters(): void
    {
        $books = OrderBooks::reduce([
            self::order(1, 100, 5.0, false),
            self::order(2, 100, 4.0, false),
            self::order(3, 100, 3.0, true),
            self::order(4, 100, 3.5, true),
            self::order(5, 200, 1.0, false),
        ], [100]);

        $this->assertSame([100], array_keys($books));
        $this->assertSame([[2, 4.0], [1, 5.0]], $books[100][34]['sell']);
        $this->assertSame([[4, 3.5], [3, 3.0]], $books[100][34]['buy']);
    }

    public function test_keeps_ten_best(): void
    {
        $orders = [];
        for ($i = 1; $i <= 15; $i++) {
            $orders[] = self::order($i, 100, (float) $i, false);
        }
        $sell = OrderBooks::reduce($orders)[100][34]['sell'];

        $this->assertCount(OrderBooks::DEPTH, $sell);
        $this->assertSame([1, 1.0], $sell[0]);
    }

    public function test_ttl_follows_expires(): void
    {
        $now = Carbon::parse('2026-11-16 12:00:00 UTC');
        $this->assertSame(240, OrderBooks::ttl('Mon, 16 Nov 2026 12:04:00 GMT', $now));
        $this->assertSame(30, OrderBooks::ttl('Mon, 16 Nov 2026 11:00:00 GMT', $now));
        $this->assertSame(3600, OrderBooks::ttl('Tue, 17 Nov 2026 12:00:00 GMT', $now));
        $this->assertSame(OrderBooks::DEFAULT_TTL, OrderBooks::ttl(null, $now));
        $this->assertSame(OrderBooks::DEFAULT_TTL, OrderBooks::ttl('garbage', $now));
    }
}
