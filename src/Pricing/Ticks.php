<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Valid EVE order prices.
 *
 * Since the "Broker Relations" release (CCP dev blog, 2020, https://www.eveonline.com/news/view/broker-relations)
 * an order price can have at most 4 significant figures, when an order is created and when it is modified.
 * ISK also has two decimals. So the step between valid prices is 10^(digits before the point - 4) ISK, and
 * never less than 0.01 ISK:
 *
 *   price range          step     example
 *   below 100            0.01     99.99
 *   100 to 999.9         0.1      999.9
 *   1,000 to 9,999       1        9,999
 *   1,000,000 and up     1,000    1,234,000
 *
 * The step shrinks right below a power of ten: the next valid price below 1,000.00 is 999.90 (not 999.99,
 * not 999), and below 1,000,000 it is 999,900.
 *
 * Everything is computed in whole cents (integers) so no floating point error can creep in.
 * Ported from eve-mcp's Market Helper (src/plugins/sell/pricing.ts), including its tests.
 */
final class Ticks
{
    /** Step between valid prices for a price of $cents (any value within that power of ten). */
    private static function stepCents(int $cents): int
    {
        $digits = strlen((string) $cents);

        return max(1, 10 ** ($digits - 4));
    }

    /** The highest valid price at or below $isk, or null below 0.01 ISK. */
    public static function roundDown(float $isk): ?float
    {
        // Floor to whole cents first; the epsilon keeps 12.34 from turning into 12.33 through 1233.9999.
        $cents = (int) floor($isk * 100 + 1e-6);
        if ($cents < 1) {
            return null;
        }
        $step = self::stepCents($cents);

        return intdiv($cents, $step) * $step / 100;
    }

    /** The highest valid price strictly below $isk: one step under it. Null at 0.01 ISK. */
    public static function below(float $isk): ?float
    {
        $cents = (int) round($isk * 100);
        if ($cents <= 1) {
            return null;
        }

        return self::roundDown(($cents - 1) / 100);
    }

    /** True when EVE accepts $isk as an order price as it is. */
    public static function isValid(float $isk): bool
    {
        return self::roundDown($isk) === round($isk, 2);
    }

    /**
     * A price as the Sell Items window's "Import Item Prices" reads it: dot as decimal separator, no thousands
     * separator, no trailing zeros (the format of github.com/sticker94/eve-multi-sell, confirmed in the client).
     */
    public static function format(float $isk): string
    {
        $text = number_format(round($isk, 2), 2, '.', '');

        return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
    }
}
