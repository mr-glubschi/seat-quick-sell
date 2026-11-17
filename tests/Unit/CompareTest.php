<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Compare;
use Glubschi\Seat\QuickSell\Pricing\Fees;
use Glubschi\Seat\QuickSell\Pricing\Reprocess;
use PHPUnit\Framework\TestCase;

class CompareTest extends TestCase
{
    private static function row(int $typeId, int $qty, ?float $price): array
    {
        return ['type_id' => $typeId, 'name' => "T$typeId", 'quantity' => $qty, 'price' => $price,
            'value' => $price === null ? null : $price * $qty];
    }

    public function test_three_ways_and_best_mix(): void
    {
        $fees = new Fees(salesTaxPct: 0.0, brokerPct: 0.0, sccPct: 0.0, safetyPct: 0.0,
            reprocessTaxPct: 0.0, reprocessOrePct: 50.0, reprocessScrapPct: 50.0);
        $rows = [self::row(1, 10, 100.0), self::row(2, 10, 100.0)];
        $books = [
            1 => ['sell' => [], 'buy' => [[11, 90.0, 100, 1]]],
            2 => ['sell' => [], 'buy' => [[12, 10.0, 100, 1]]],
            34 => ['sell' => [], 'buy' => [[13, 1.0, 100000, 1]]],
        ];
        // item 1: 10 units -> 50% of 100 Tritanium each = 500 x 1 ISK; item 2: 10 x 50% x 400 = 2000 ISK
        $reprocessing = [
            1 => ['portion' => 1, 'ore_skill' => null, 'materials' => [34 => 100]],
            2 => ['portion' => 1, 'ore_skill' => null, 'materials' => [34 => 400]],
        ];

        $r = Compare::run($rows, $books, $reprocessing, [Reprocess::SCRAPMETAL_PROCESSING => 0], $fees, []);

        $this->assertEqualsWithDelta(900.0, $r['rows'][0]['ways']['instant']['net'], 1e-9);
        $this->assertEqualsWithDelta(500.0, $r['rows'][0]['ways']['reprocess']['net'], 1e-9);
        $this->assertEqualsWithDelta(1000.0, $r['rows'][0]['ways']['market']['net'], 1e-9);
        $this->assertSame('market', $r['rows'][0]['best']);

        $this->assertEqualsWithDelta(2000.0, $r['rows'][1]['ways']['reprocess']['net'], 1e-9);
        $this->assertSame('reprocess', $r['rows'][1]['best']);

        $this->assertEqualsWithDelta(1000.0, $r['totals']['instant']['net'], 1e-9);
        $this->assertEqualsWithDelta(2500.0, $r['totals']['reprocess']['net'], 1e-9);
        $this->assertEqualsWithDelta(2000.0, $r['totals']['market']['net'], 1e-9);
        $this->assertEqualsWithDelta(3000.0, $r['totals']['mix']['net'], 1e-9);
    }

    public function test_materials_share_the_buy_orders(): void
    {
        $fees = new Fees(0.0, 0.0, 0.0, 0.0, 0.0, 50.0, 100.0);
        $rows = [self::row(1, 1, null), self::row(2, 1, null)];
        // 150 Tritanium in all, but the buy orders take 100 at 2 ISK and 50 at 1 ISK: 250 ISK, 5/3 ISK per unit
        $books = [34 => ['sell' => [], 'buy' => [[1, 2.0, 100, 1], [2, 1.0, 1000, 1]]]];
        $reprocessing = [
            1 => ['portion' => 1, 'ore_skill' => null, 'materials' => [34 => 100]],
            2 => ['portion' => 1, 'ore_skill' => null, 'materials' => [34 => 50]],
        ];

        $r = Compare::run($rows, $books, $reprocessing, [], $fees, []);

        $this->assertEqualsWithDelta(250.0, $r['totals']['reprocess']['net'], 1e-9);
        $this->assertEqualsWithDelta(100 * 250 / 150, $r['rows'][0]['ways']['reprocess']['net'], 1e-9);
        $this->assertNull($r['rows'][0]['ways']['instant']);
        $this->assertNull($r['rows'][0]['ways']['market']);
    }

    public function test_items_without_materials_or_offers(): void
    {
        $fees = Fees::from(false, []);
        $r = Compare::run([self::row(1, 5, null)], [], [], [], $fees, []);

        $this->assertSame(['instant' => null, 'reprocess' => null, 'market' => null], $r['rows'][0]['ways']);
        $this->assertNull($r['rows'][0]['best']);
    }
}
