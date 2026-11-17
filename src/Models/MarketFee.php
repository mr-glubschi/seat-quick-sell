<?php

namespace Glubschi\Seat\QuickSell\Models;

use Glubschi\Seat\QuickSell\Pricing\Fees;
use Seat\Services\Models\ExtensibleModel;

/**
 * The fees a user last used at one market (entered by hand, see Fees). Extends SeAT's ExtensibleModel, as the
 * development tips ask.
 *
 * @property int $user_id
 * @property int $location_id
 * @property float|null $sales_tax_pct
 * @property float|null $broker_pct
 * @property float|null $scc_pct
 * @property float|null $safety_pct
 * @property float|null $reprocess_tax_pct
 * @property float|null $reprocess_ore_pct
 * @property float|null $reprocess_scrap_pct
 */
class MarketFee extends ExtensibleModel
{
    protected $table = 'quicksell_market_fees';

    protected $fillable = ['user_id', 'location_id', ...Fees::FIELDS];

    protected $casts = [
        'sales_tax_pct' => 'float',
        'broker_pct' => 'float',
        'scc_pct' => 'float',
        'safety_pct' => 'float',
        'reprocess_tax_pct' => 'float',
        'reprocess_ore_pct' => 'float',
        'reprocess_scrap_pct' => 'float',
    ];

    /** @return array<string, float>  the values saved for this market (unsaved ones left out) */
    public static function savedFor(int $userId, int $locationId): array
    {
        $row = static::where('user_id', $userId)->where('location_id', $locationId)->first();

        return $row ? array_filter($row->only(Fees::FIELDS), fn ($v) => $v !== null) : [];
    }

    /** Keeps the values used for a quote as this market's last values. */
    public static function remember(int $userId, int $locationId, Fees $fees): void
    {
        static::updateOrCreate(['user_id' => $userId, 'location_id' => $locationId], $fees->toArray());
    }
}
