<?php

namespace Glubschi\Seat\QuickSell\Models;

use Seat\Services\Models\ExtensibleModel;

/**
 * Fees a user corrected for one market. Extends SeAT's ExtensibleModel, as the development tips ask.
 *
 * @property int $user_id
 * @property int $location_id
 * @property float|null $broker_pct
 * @property float|null $scc_pct
 * @property float|null $safety_pct
 */
class MarketFee extends ExtensibleModel
{
    protected $table = 'quicksell_market_fees';

    protected $fillable = ['user_id', 'location_id', 'broker_pct', 'scc_pct', 'safety_pct'];

    protected $casts = [
        'broker_pct' => 'float',
        'scc_pct' => 'float',
        'safety_pct' => 'float',
    ];

    /** @return array{broker_pct?: float|null, scc_pct?: float|null, safety_pct?: float|null} */
    public static function savedFor(int $userId, int $locationId): array
    {
        $row = static::where('user_id', $userId)->where('location_id', $locationId)->first();

        return $row ? array_filter($row->only(['broker_pct', 'scc_pct', 'safety_pct']), fn ($v) => $v !== null) : [];
    }
}
