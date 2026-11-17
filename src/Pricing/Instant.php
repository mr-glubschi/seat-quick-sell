<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Selling a stack right now: into the buy orders at the market, best price first.
 *
 * Only buy orders placed at this market are counted. A buy order placed elsewhere with a range that covers
 * this market could also be served, but its range is not kept, so the result errs on the low side.
 * A buy order with a minimum volume is used only while at least that many units are left to sell.
 */
final class Instant
{
    /**
     * @param  array<int, array>  $buyOrders  [order_id, price, volume_remain, min_volume], best first
     * @param  array<int, true>  $ownOrderIds  the user's own orders, which are skipped
     * @return array{quantity: int, gross: float}  units sold and ISK received before tax
     */
    public static function fill(int $quantity, array $buyOrders, array $ownOrderIds = []): array
    {
        $left = $quantity;
        $gross = 0.0;
        foreach ($buyOrders as $order) {
            if ($left <= 0) {
                break;
            }
            [$orderId, $price] = $order;
            $volume = (int) ($order[2] ?? 0);
            $minimum = max(1, (int) ($order[3] ?? 1));
            if (isset($ownOrderIds[$orderId]) || $volume <= 0 || $left < $minimum) {
                continue;
            }
            $sold = min($left, $volume);
            $gross += $sold * (float) $price;
            $left -= $sold;
        }

        return ['quantity' => $quantity - $left, 'gross' => $gross];
    }
}
