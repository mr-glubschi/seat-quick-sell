<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Turns order books into sell prices. Pure: no database, no ESI, so every rule is unit tested.
 *
 * The rule, as decided for Quick Sell:
 * - price = the lowest sell order of other players at the market, one valid price step lower;
 * - the user's own orders are ignored when finding that lowest sell;
 * - when nobody else sells the item there, the Jita sell price is used and the row is marked;
 * - Jita sell is the anchor: every row shows how far the price is above or below it, in percent.
 *
 * A book is ['sell' => [[order_id, price], ...], 'buy' => [[order_id, price], ...]], best orders first,
 * or null when it could not be fetched.
 */
final class Quote
{
    /**
     * @param  array<int, array{type_id: int, name: string, quantity: int}>  $items
     * @param  array<int, array|null>  $books  market books by type ID
     * @param  array<int, array|null>  $jitaBooks  Jita 4-4 books by type ID
     * @param  array<int, true>  $ownOrderIds  IDs of the user's own open orders (any character)
     * @param  array<int, true>  $ownSellTypes  type IDs the user has an open sell order for at this market
     * @return array<int, array<string, mixed>>
     */
    public static function rows(array $items, array $books, array $jitaBooks, array $ownOrderIds, array $ownSellTypes): array
    {
        $rows = [];
        foreach ($items as $item) {
            $typeId = $item['type_id'];
            $book = $books[$typeId] ?? null;
            $jita = $jitaBooks[$typeId] ?? null;

            $sell = self::best($book['sell'] ?? [], $ownOrderIds);
            $buy = self::best($book['buy'] ?? [], $ownOrderIds);
            $jitaSell = self::best($jita['sell'] ?? [], $ownOrderIds);
            $jitaBuy = self::best($jita['buy'] ?? [], $ownOrderIds);

            $noOffer = $book !== null && $sell === null;
            if ($sell !== null) {
                $price = Ticks::below($sell);
            } else {
                // Nobody else sells it here: Jita sell, which is already a valid price.
                $price = $jitaSell;
            }

            $rows[] = [
                'type_id' => $typeId,
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'station_sell' => $sell,
                'station_buy' => $buy,
                'jita_sell' => $jitaSell,
                'jita_buy' => $jitaBuy,
                'price' => $price,
                'value' => $price === null ? null : $price * $item['quantity'],
                'vs_jita_pct' => ($price !== null && $jitaSell) ? ($price - $jitaSell) / $jitaSell * 100 : null,
                'flags' => [
                    // The market's order book could not be read (no access, ESI error).
                    'no_data' => $book === null,
                    // Nobody but the user sells it here; the price is Jita sell (shown in blue).
                    'no_offer' => $noOffer,
                    // The user already has a sell order for it here (ignored when undercutting).
                    'own_order' => isset($ownSellTypes[$typeId]),
                    // The best buy order pays at least as much: selling into it is better.
                    'buy_above' => $price !== null && $buy !== null && $buy >= $price,
                ],
            ];
        }

        return $rows;
    }

    /** Lines for "Import Item Prices": name, tab, price, tab, quantity. Rows without a price are left out. */
    public static function importText(array $rows): string
    {
        $lines = [];
        foreach ($rows as $row) {
            if ($row['price'] !== null) {
                $lines[] = $row['name'] . "\t" . Ticks::format($row['price']) . "\t" . $row['quantity'];
            }
        }

        return implode("\n", $lines);
    }

    /** Price of the first order that is not the user's own. Books are sorted best first. */
    private static function best(array $orders, array $ownOrderIds): ?float
    {
        foreach ($orders as [$orderId, $price]) {
            if (! isset($ownOrderIds[$orderId])) {
                return (float) $price;
            }
        }

        return null;
    }
}
