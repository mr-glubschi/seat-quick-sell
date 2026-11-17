<?php

namespace Glubschi\Seat\QuickSell\Markets;

use Glubschi\Seat\QuickSell\Jobs\RegionTypeOrders;
use Glubschi\Seat\QuickSell\Jobs\StructureOrders;

/**
 * The order books a quote needs: what is in the cache, and the SeAT jobs that fetch what is missing.
 *
 * Jita 4-4 (The Forge) is always needed as the anchor. A station in The Forge shares the jobs with Jita, one
 * request per item for both. A structure market is fetched whole, with the first character that has the
 * structure market scope; characters docked there are tried first, since they are the likeliest to have access.
 *
 * The market needs the books of the items and of the materials they reprocess into; Jita only those of the items.
 */
final class Books
{
    /**
     * Cached books.
     *
     * @param  int[]  $typeIds  types needed at the market (items and materials)
     * @param  int[]|null  $jitaTypeIds  types needed in Jita (the items); null = the same as $typeIds
     * @return array{market: array<int, array|null>, jita: array<int, array>, complete: bool}
     *   market: book by type, null where the market could not be read; jita: books found in the cache;
     *   complete: everything needed is cached (or known to be unreadable)
     */
    public static function cached(Market $market, array $typeIds, ?array $jitaTypeIds = null): array
    {
        $jitaTypeIds ??= $typeIds;
        $jita = OrderBooks::station(Market::JITA_STATION, $jitaTypeIds);
        $complete = count($jita) === count($jitaTypeIds);

        if ($market->isJita()) {
            $books = OrderBooks::station(Market::JITA_STATION, $typeIds);
            $complete = count($books) === count($typeIds);
        } elseif ($market->isStructure()) {
            $books = OrderBooks::structure($market->locationId, $typeIds);
            if ($books === null) {
                $complete = $complete && OrderBooks::isDenied($market->locationId);
                $books = [];
            }
        } else {
            $books = OrderBooks::station($market->locationId, $typeIds);
            $complete = $complete && count($books) === count($typeIds);
        }

        $market = [];
        foreach ($typeIds as $typeId) {
            $market[$typeId] = $books[$typeId] ?? null;
        }

        return ['market' => $market, 'jita' => $jita, 'complete' => $complete];
    }

    /**
     * Jobs that fetch the books missing from the cache, split by SeAT queue.
     *
     * @param  int[]  $typeIds  types needed at the market (items and materials)
     * @param  int[]  $characterIds  the user's characters, most likely to have structure access first
     * @param  int[]|null  $jitaTypeIds  types needed in Jita (the items); null = the same as $typeIds
     * @return array{public: RegionTypeOrders[], characters: StructureOrders[]}
     */
    public static function jobs(Market $market, array $typeIds, array $characterIds, ?array $jitaTypeIds = null): array
    {
        $jitaTypeIds = $market->isJita() ? array_values(array_unique(array_merge($typeIds, $jitaTypeIds ?? []))) : ($jitaTypeIds ?? $typeIds);
        $missingJita = array_diff($jitaTypeIds, array_keys(OrderBooks::station(Market::JITA_STATION, $jitaTypeIds)));
        $public = [];
        $characters = [];

        if ($market->isStructure()) {
            if (OrderBooks::structure($market->locationId, []) === null && ! OrderBooks::isDenied($market->locationId)) {
                $token = StructureOrders::firstToken($characterIds);
                if ($token === null) {
                    // No character may read structure markets at all: nothing to wait for.
                    OrderBooks::markDenied($market->locationId);
                } else {
                    $rest = array_values(array_diff($characterIds, [$token->character_id]));
                    $characters[] = new StructureOrders($token, $market->locationId, $rest);
                }
            }
        } elseif (! $market->isJita()) {
            $missing = array_diff($typeIds, array_keys(OrderBooks::station($market->locationId, $typeIds)));
            if ($market->regionId === Market::THE_FORGE) {
                // One request per item serves the station and Jita.
                foreach (array_unique(array_merge($missing, $missingJita)) as $typeId) {
                    $public[] = new RegionTypeOrders(Market::THE_FORGE, $typeId, [$market->locationId, Market::JITA_STATION]);
                }

                return ['public' => $public, 'characters' => $characters];
            }
            foreach ($missing as $typeId) {
                $public[] = new RegionTypeOrders($market->regionId, $typeId, [$market->locationId]);
            }
        }

        foreach ($missingJita as $typeId) {
            $public[] = new RegionTypeOrders(Market::THE_FORGE, $typeId, [Market::JITA_STATION]);
        }

        return ['public' => $public, 'characters' => $characters];
    }
}
