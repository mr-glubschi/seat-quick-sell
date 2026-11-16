<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Fees;
use PHPUnit\Framework\TestCase;

class FeesTest extends TestCase
{
    public function test_skill_formulas(): void
    {
        $this->assertEqualsWithDelta(7.5, Fees::salesTaxPct(0), 1e-9);
        $this->assertEqualsWithDelta(3.375, Fees::salesTaxPct(5), 1e-9);
        $this->assertEqualsWithDelta(3.0, Fees::npcBrokerPct(0), 1e-9);
        $this->assertEqualsWithDelta(1.5, Fees::npcBrokerPct(5), 1e-9);
    }

    public function test_npc_station_defaults(): void
    {
        $fees = Fees::for(false, 5, 5);
        $this->assertSame(0.0, $fees->sccPct);
        $this->assertEqualsWithDelta(1.5, $fees->brokerPct, 1e-9);
        $this->assertSame(0.0, $fees->safetyPct);
    }

    public function test_structure_needs_broker_fee_entered(): void
    {
        $fees = Fees::for(true, 5, 5);
        $this->assertNull($fees->brokerPct);
        $this->assertSame(0.5, $fees->sccPct);

        $saved = Fees::for(true, 5, 5, ['broker_pct' => 1.0, 'safety_pct' => 2.0]);
        $this->assertSame(1.0, $saved->brokerPct);
        $this->assertSame(2.0, $saved->safetyPct);
        $this->assertSame(0.5, $saved->sccPct);
    }

    public function test_totals_with_minimum_broker_fee(): void
    {
        $fees = new Fees(sccPct: 0.5, brokerPct: 1.0, salesTaxPct: 3.375, safetyPct: 0.0);
        $t = $fees->totals([1000000.0, 5000.0]);

        $this->assertSame(1005000.0, $t['gross']);
        // 1% of 1,000,000 = 10,000; 1% of 5,000 = 50, raised to the 100 ISK minimum.
        $this->assertEqualsWithDelta(10100.0, $t['broker'], 1e-6);
        $this->assertEqualsWithDelta(5025.0, $t['scc'], 1e-6);
        $this->assertEqualsWithDelta(33918.75, $t['sales_tax'], 1e-6);
        $this->assertEqualsWithDelta(1005000 - 10100 - 5025 - 33918.75, $t['net'], 1e-6);
    }

    public function test_unknown_broker_fee_counts_nothing(): void
    {
        $t = (new Fees(0.5, null, 3.375, 0.0))->totals([1000.0]);
        $this->assertSame(0.0, $t['broker']);
    }
}
