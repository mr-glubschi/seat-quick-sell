<?php

namespace Glubschi\Seat\QuickSell\Markets;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Order books in Laravel's cache (Redis in SeAT), kept exactly as long as ESI says its data is valid.
 *
 * Only the jobs write here; the page only reads. A book keeps the 10 best sell and buy orders of an item at
 * one place as [order_id, price] pairs, best first: enough to skip the user's own orders and find the price
 * to undercut, small enough to cache whole structure markets. Keys carry the author and package prefix,
 * as SeAT's development tips ask for Redis records.
 */
final class OrderBooks
{
    public const PREFIX = 'glubschi.quicksell.';

    public const DEPTH = 10;

    /** Used when a response carries no usable Expires header (ESI caches market orders for 5 minutes). */
    public const DEFAULT_TTL = 300;

    public static function stationKey(int $locationId, int $typeId): string
    {
        return self::PREFIX . "book.$locationId.$typeId";
    }

    public static function structureKey(int $structureId): string
    {
        return self::PREFIX . "structure.$structureId";
    }

    public static function deniedKey(int $structureId): string
    {
        return self::PREFIX . "denied.$structureId";
    }

    /**
     * Groups raw ESI orders into books per location and type.
     *
     * @param  iterable<object>  $orders  ESI order objects (order_id, type_id, location_id, price, is_buy_order)
     * @param  int[]|null  $locations  keep only these locations (null = all)
     * @return array<int, array<int, array{sell: array, buy: array}>>  [location][type] => book
     */
    public static function reduce(iterable $orders, ?array $locations = null): array
    {
        $keep = $locations === null ? null : array_flip($locations);
        $books = [];
        foreach ($orders as $o) {
            if ($keep !== null && ! isset($keep[$o->location_id])) {
                continue;
            }
            $side = $o->is_buy_order ? 'buy' : 'sell';
            $books[$o->location_id][$o->type_id] ??= ['sell' => [], 'buy' => []];
            $books[$o->location_id][$o->type_id][$side][] = [(int) $o->order_id, (float) $o->price];
        }
        foreach ($books as &$types) {
            foreach ($types as &$book) {
                usort($book['sell'], fn ($a, $b) => $a[1] <=> $b[1]);
                usort($book['buy'], fn ($a, $b) => $b[1] <=> $a[1]);
                $book['sell'] = array_slice($book['sell'], 0, self::DEPTH);
                $book['buy'] = array_slice($book['buy'], 0, self::DEPTH);
            }
        }

        return $books;
    }

    /** Seconds until an ESI Expires header, at least 30 so a just-expired response is not refetched in a loop. */
    public static function ttl(?string $expires, ?Carbon $now = null): int
    {
        if (! $expires) {
            return self::DEFAULT_TTL;
        }
        try {
            $seconds = ($now ?? Carbon::now())->diffInSeconds(Carbon::parse($expires), false);
        } catch (\Throwable) {
            return self::DEFAULT_TTL;
        }

        return (int) max(30, min($seconds, 3600));
    }

    public static function putStation(int $locationId, int $typeId, array $book, int $ttl): void
    {
        Cache::put(self::stationKey($locationId, $typeId), $book, $ttl);
    }

    public static function putStructure(int $structureId, array $booksByType, int $ttl): void
    {
        Cache::put(self::structureKey($structureId), $booksByType, $ttl);
        Cache::forget(self::deniedKey($structureId));
    }

    /** No character could read the structure's market; remembered for 5 minutes so the page can say so. */
    public static function markDenied(int $structureId): void
    {
        Cache::put(self::deniedKey($structureId), true, self::DEFAULT_TTL);
    }

    public static function isDenied(int $structureId): bool
    {
        return (bool) Cache::get(self::deniedKey($structureId), false);
    }

    /**
     * Cached books of a station for the given types; a type that is not cached is missing from the result.
     *
     * @return array<int, array>
     */
    public static function station(int $locationId, array $typeIds): array
    {
        $books = [];
        foreach ($typeIds as $typeId) {
            $book = Cache::get(self::stationKey($locationId, $typeId));
            if ($book !== null) {
                $books[$typeId] = $book;
            }
        }

        return $books;
    }

    /**
     * Cached books of a structure for the given types, or null when the structure's market is not cached.
     * A type nobody trades there gets an empty book.
     *
     * @return array<int, array>|null
     */
    public static function structure(int $structureId, array $typeIds): ?array
    {
        $all = Cache::get(self::structureKey($structureId));
        if ($all === null) {
            return null;
        }
        $books = [];
        foreach ($typeIds as $typeId) {
            $books[$typeId] = $all[$typeId] ?? ['sell' => [], 'buy' => []];
        }

        return $books;
    }
}
