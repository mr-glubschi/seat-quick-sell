<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Fees of sell orders as the Sell Items window lists them, in percent of the order value.
 *
 * - Sales tax: 7.5% minus 11% of that per level of Accounting (skill 16622; its SDE description says
 *   "Each level of skill reduces sales tax by 11%. Sales tax starts at 7.5%."), so 3.375% at level V.
 * - Broker's fee in an NPC station: 3% minus 0.3 points per level of Broker Relations (skill 3446, SDE
 *   description). Standings lower it further and are not included, so this is a suggestion the user can correct.
 * - Broker's fee in an Upwell structure: set by the owner and not available through ESI; the user enters it.
 * - SCC surcharge: 0.5% in structures (EVE University wiki, "Trading").
 * - Safety tax: shown by the client, 0% unless entered.
 * The broker's fee is at least 100 ISK per order.
 */
final class Fees
{
    public const ACCOUNTING = 16622;

    public const BROKER_RELATIONS = 3446;

    public const MIN_BROKER_FEE = 100.0;

    public const STRUCTURE_SCC_PCT = 0.5;

    /**
     * @param  float|null  $brokerPct  null = unknown (structure fee not entered yet); no broker's fee is counted
     */
    public function __construct(
        public readonly float $sccPct,
        public readonly ?float $brokerPct,
        public readonly float $salesTaxPct,
        public readonly float $safetyPct,
    ) {
    }

    public static function salesTaxPct(int $accounting): float
    {
        return 7.5 * (1 - 0.11 * $accounting);
    }

    public static function npcBrokerPct(int $brokerRelations): float
    {
        return 3 - 0.3 * $brokerRelations;
    }

    /**
     * Fees for a market. Saved values (entered by the user for this market) win over the defaults.
     *
     * @param  array{broker_pct?: float|null, scc_pct?: float|null, safety_pct?: float|null}  $saved
     */
    public static function for(bool $structure, int $accounting, int $brokerRelations, array $saved = []): self
    {
        $broker = $saved['broker_pct'] ?? ($structure ? null : self::npcBrokerPct($brokerRelations));

        return new self(
            sccPct: (float) ($saved['scc_pct'] ?? ($structure ? self::STRUCTURE_SCC_PCT : 0.0)),
            brokerPct: $broker === null ? null : (float) $broker,
            salesTaxPct: self::salesTaxPct($accounting),
            safetyPct: (float) ($saved['safety_pct'] ?? 0.0),
        );
    }

    /**
     * ISK of each fee for a list of order values (price x quantity per order).
     *
     * @param  float[]  $orderValues
     * @return array{gross: float, scc: float, broker: float, sales_tax: float, safety: float, net: float}
     */
    public function totals(array $orderValues): array
    {
        $gross = array_sum($orderValues);
        $broker = 0.0;
        if ($this->brokerPct) {
            foreach ($orderValues as $value) {
                $broker += max(self::MIN_BROKER_FEE, $value * $this->brokerPct / 100);
            }
        }
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
