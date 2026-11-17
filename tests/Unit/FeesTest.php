<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Fees;
use PHPUnit\Framework\TestCase;

class FeesTest extends TestCase
{
    public function test_starting_values(): void
    {
        $npc = Fees::from(false, []);
        $this->assertSame(7.5, $npc->salesTaxPct);
        $this->assertSame(3.0, $npc->brokerPct);
        $this->assertSame(0.0, $npc->sccPct);
        $this->assertSame(5.0, $npc->reprocessTaxPct);
        $this->assertSame(50.0, $npc->reprocessOrePct);

        $structure = Fees::from(true, []);
        $this->assertNull($structure->brokerPct);
        $this->assertSame(0.5, $structure->sccPct);
        $this->assertSame(0.0, $structure->reprocessTaxPct);
    }

    public function test_entered_values_win_and_empty_fields_fall_back(): void
    {
        $fees = Fees::from(true, ['sales_tax_pct' => '3.375', 'broker_pct' => '1', 'scc_pct' => '', 'unknown' => 9]);

        $this->assertSame(3.375, $fees->salesTaxPct);
        $this->assertSame(1.0, $fees->brokerPct);
        $this->assertSame(0.5, $fees->sccPct);
        $this->assertSame(Fees::FIELDS, array_keys($fees->toArray()));
    }

    public function test_instant_and_market_net(): void
    {
        $fees = new Fees(3.375, 1.0, 0.5, 0.0, 0.0, 50.0, 50.0);

        $this->assertEqualsWithDelta(966250.0, $fees->instantNet(1000000.0), 1e-6);
        // 1,000,000 - 0.5% SCC - 3.375% tax - 1% broker
        $this->assertEqualsWithDelta(951250.0, $fees->marketNet(1000000.0), 1e-6);
        // broker's fee at least 100 ISK
        $this->assertEqualsWithDelta(5000 * (1 - 0.03875) - 100, $fees->marketNet(5000.0), 1e-6);
    }

    public function test_totals_with_minimum_broker_fee(): void
    {
        $t = (new Fees(3.375, 1.0, 0.5, 0.0, 0.0, 50.0, 50.0))->totals([1000000.0, 5000.0]);

        $this->assertSame(1005000.0, $t['gross']);
        $this->assertEqualsWithDelta(10100.0, $t['broker'], 1e-6);
        $this->assertEqualsWithDelta(5025.0, $t['scc'], 1e-6);
        $this->assertEqualsWithDelta(33918.75, $t['sales_tax'], 1e-6);
        $this->assertEqualsWithDelta(1005000 - 10100 - 5025 - 33918.75, $t['net'], 1e-6);
    }

    public function test_unknown_broker_fee_counts_nothing(): void
    {
        $this->assertSame(0.0, (new Fees(3.375, null, 0.5, 0.0, 0.0, 50.0, 50.0))->brokerFee(1000.0));
    }
}
