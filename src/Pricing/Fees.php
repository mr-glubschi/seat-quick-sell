<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Fees and reprocessing settings of one market, in percent, as the user entered them.
 *
 * Since v0.4.0 every value is entered by hand and the last values are saved per user and market: standings,
 * structure owners and implants change them in ways ESI does not show, and the game shows the real values in
 * the Sell Items and Reprocessing windows. Until a market has saved values, the game's starting values are used:
 *
 * - sales tax 7.5% (before Accounting), broker's fee 3% in NPC stations (before Broker Relations and
 *   standings), unknown in structures (set by the owner);
 * - SCC surcharge 0.5% in structures (EVE University wiki, "Trading"); safety tax 0%;
 * - reprocessing tax 5% in NPC stations at zero standings, 0% in structures until entered; reprocessing base
 *   50% (EVE University wiki, "Reprocessing").
 *
 * The broker's fee is at least 100 ISK per order.
 */
final class Fees
{
    public const MIN_BROKER_FEE = 100.0;

    /** The values a user enters, in the order the page shows them. */
    public const FIELDS = [
        'sales_tax_pct', 'broker_pct', 'scc_pct', 'safety_pct',
        'reprocess_tax_pct', 'reprocess_ore_pct', 'reprocess_scrap_pct',
    ];

    public function __construct(
        public readonly float $salesTaxPct,
        public readonly ?float $brokerPct,
        public readonly float $sccPct,
        public readonly float $safetyPct,
        public readonly float $reprocessTaxPct,
        public readonly float $reprocessOrePct,
        public readonly float $reprocessScrapPct,
    ) {
    }

    /** @return array<string, float|null> starting values of a market without saved fees */
    public static function defaults(bool $structure): array
    {
        return [
            'sales_tax_pct' => 7.5,
            'broker_pct' => $structure ? null : 3.0,
            'scc_pct' => $structure ? 0.5 : 0.0,
            'safety_pct' => 0.0,
            'reprocess_tax_pct' => $structure ? 0.0 : 5.0,
            'reprocess_ore_pct' => 50.0,
            'reprocess_scrap_pct' => 50.0,
        ];
    }

    /** @param  array<string, float|int|string|null>  $values  entered values; missing ones fall back to the defaults */
    public static function from(bool $structure, array $values): self
    {
        $v = array_merge(self::defaults($structure), array_filter(
            array_intersect_key($values, array_flip(self::FIELDS)), fn ($x) => $x !== null && $x !== ''));
        $f = fn (string $k) => $v[$k] === null ? null : (float) $v[$k];

        return new self($f('sales_tax_pct'), $f('broker_pct'), $f('scc_pct'), $f('safety_pct'),
            $f('reprocess_tax_pct'), $f('reprocess_ore_pct'), $f('reprocess_scrap_pct'));
    }

    public function toArray(): array
    {
        return [
            'sales_tax_pct' => $this->salesTaxPct,
            'broker_pct' => $this->brokerPct,
            'scc_pct' => $this->sccPct,
            'safety_pct' => $this->safetyPct,
            'reprocess_tax_pct' => $this->reprocessTaxPct,
            'reprocess_ore_pct' => $this->reprocessOrePct,
            'reprocess_scrap_pct' => $this->reprocessScrapPct,
        ];
    }

    /** ISK left of an instant sale (into buy orders): only the sales tax applies. */
    public function instantNet(float $gross): float
    {
        return $gross * (1 - $this->salesTaxPct / 100);
    }

    /** Broker's fee of one sell order. */
    public function brokerFee(float $orderValue): float
    {
        return $this->brokerPct ? max(self::MIN_BROKER_FEE, $orderValue * $this->brokerPct / 100) : 0.0;
    }

    /** ISK left of one sell order once it has sold: minus broker's fee, SCC surcharge, sales and safety tax. */
    public function marketNet(float $orderValue): float
    {
        return $orderValue * (1 - ($this->sccPct + $this->salesTaxPct + $this->safetyPct) / 100) - $this->brokerFee($orderValue);
    }

    /**
     * ISK of each fee for a list of sell orders (price x quantity per order), as the Sell Items window adds them up.
     *
     * @param  float[]  $orderValues
     * @return array{gross: float, scc: float, broker: float, sales_tax: float, safety: float, net: float}
     */
    public function totals(array $orderValues): array
    {
        $gross = array_sum($orderValues);
        $broker = array_sum(array_map([$this, 'brokerFee'], $orderValues));
        $scc = $gross * $this->sccPct / 100;
        $salesTax = $gross * $this->salesTaxPct / 100;
        $safety = $gross * $this->safetyPct / 100;

        return [
            'gross' => $gross,
            'scc' => $scc,
            'broker' => $broker,
            'sales_tax' => $salesTax,
            'safety' => $safety,
            'net' => $gross - $scc - $broker - $salesTax - $safety,
        ];
    }
}
