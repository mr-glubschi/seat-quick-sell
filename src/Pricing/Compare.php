<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * The three ways to turn a stack into ISK at this market, side by side, so the user can choose:
 *
 * - instant: sell into the buy orders here now (sales tax only);
 * - reprocess: reprocess here and sell the materials into the buy orders here now (sales tax on the materials);
 * - market: put up a sell order one price step under the cheapest offer and wait (all fees; the current function).
 *
 * All amounts are net ISK. The materials of all items are added up before they are sold, as the user would sell
 * one stack of Tritanium, and each item gets its share of what that stack brings. "best" is the way with the most
 * ISK; the "mix" total takes the best way of every item.
 */
final class Compare
{
    public const WAYS = ['instant', 'reprocess', 'market'];

    /**
     * @param  array<int, array>  $rows  rows from Quote::rows (type_id, quantity, value, ...)
     * @param  array<int, array|null>  $books  books at the market by type ID (items and materials)
     * @param  array<int, array{portion: int, ore_skill: int|null, materials: array<int, int>}>  $reprocessing  by type ID;
     *                                                                                                   items missing here cannot be reprocessed
     * @param  array<int, int>  $skills  the chosen character's skill levels by skill ID
     * @param  array<int, true>  $ownOrderIds
     * @return array{rows: array, totals: array<string, array{gross: float, net: float}>, materials: array<int, array>}
     */
    public static function run(array $rows, array $books, array $reprocessing, array $skills, Fees $fees, array $ownOrderIds): array
    {
        // Reprocessing output of every row, then all materials added up.
        $outputs = [];
        $materialTotals = [];
        foreach ($rows as $i => $row) {
            $info = $reprocessing[$row['type_id']] ?? null;
            if ($info === null || $info['materials'] === []) {
                continue;
            }
            $yield = Reprocess::yield($info['ore_skill'], $fees->reprocessOrePct, $fees->reprocessScrapPct, $skills);
            $outputs[$i] = Reprocess::output($row['quantity'], $info['portion'], $info['materials'], $yield, $fees->reprocessTaxPct)
                + ['yield' => $yield];
            foreach ($outputs[$i]['materials'] as $materialId => $qty) {
                $materialTotals[$materialId] = ($materialTotals[$materialId] ?? 0) + $qty;
            }
        }

        // Each material sold at once into the buy orders here: ISK per unit of the whole stack.
        $materials = [];
        foreach ($materialTotals as $materialId => $qty) {
            $fill = Instant::fill($qty, $books[$materialId]['buy'] ?? [], $ownOrderIds);
            $materials[$materialId] = ['quantity' => $qty, 'sold' => $fill['quantity'], 'gross' => $fill['gross'],
                'per_unit' => $fill['gross'] / $qty];
        }

        $totals = array_fill_keys([...self::WAYS, 'mix'], ['gross' => 0.0, 'net' => 0.0]);
        foreach ($rows as $i => &$row) {
            $ways = [];

            $fill = Instant::fill($row['quantity'], $books[$row['type_id']]['buy'] ?? [], $ownOrderIds);
            $ways['instant'] = $fill['quantity'] > 0 ? [
                'gross' => $fill['gross'],
                'net' => $fees->instantNet($fill['gross']),
                'sold' => $fill['quantity'],
                'per_unit' => $fill['gross'] / $fill['quantity'],
            ] : null;

            $out = $outputs[$i] ?? null;
            $gross = 0.0;
            foreach ($out['materials'] ?? [] as $materialId => $qty) {
                $gross += $qty * $materials[$materialId]['per_unit'];
            }
            $ways['reprocess'] = ($out !== null && $out['portions'] > 0 && $gross > 0) ? [
                'gross' => $gross,
                'net' => $fees->instantNet($gross),
                'portions' => $out['portions'],
                'yield' => $out['yield'],
                'per_unit' => $gross / $row['quantity'],
            ] : null;

            $ways['market'] = $row['value'] ? [
                'gross' => $row['value'],
                'net' => $fees->marketNet($row['value']),
                'per_unit' => $row['price'],
            ] : null;

            $best = null;
            foreach ($ways as $way => $result) {
                if ($result !== null) {
                    $totals[$way]['gross'] += $result['gross'];
                    $totals[$way]['net'] += $result['net'];
                    if ($best === null || $result['net'] > $ways[$best]['net']) {
                        $best = $way;
                    }
                }
            }
            if ($best !== null) {
                $totals['mix']['gross'] += $ways[$best]['gross'];
                $totals['mix']['net'] += $ways[$best]['net'];
            }
            $row['ways'] = $ways;
            $row['best'] = $best;
        }
        unset($row);

        return ['rows' => $rows, 'totals' => $totals, 'materials' => $materials];
    }
}
