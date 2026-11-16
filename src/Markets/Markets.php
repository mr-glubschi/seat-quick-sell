<?php

namespace Glubschi\Seat\QuickSell\Markets;

use Illuminate\Support\Facades\DB;

/**
 * Finds markets in data SeAT already has:
 * - NPC stations from the SDE table staStations (complete, with system and region);
 * - Upwell structures from universe_structures, which SeAT fills with every structure it meets in the
 *   characters' assets, orders and locations (so a structure SeAT has never seen cannot be searched);
 * - where the user's characters are docked, from character_locations.
 */
final class Markets
{
    public static function find(string $id): ?Market
    {
        if (! preg_match('/^(station|structure):(\d+)$/', $id, $m)) {
            return null;
        }

        return $m[1] === 'station' ? self::station((int) $m[2]) : self::structure((int) $m[2]);
    }

    public static function station(int $stationId): ?Market
    {
        $row = DB::table('staStations')->where('stationID', $stationId)
            ->first(['stationID', 'stationName', 'solarSystemID', 'regionID']);

        return $row ? new Market('station', (int) $row->stationID, $row->stationName, (int) $row->solarSystemID, (int) $row->regionID) : null;
    }

    public static function structure(int $structureId): ?Market
    {
        $row = DB::table('universe_structures as s')
            ->leftJoin('solar_systems as ss', 'ss.system_id', '=', 's.solar_system_id')
            ->where('s.structure_id', $structureId)
            ->first(['s.structure_id', 's.name', 's.solar_system_id', 'ss.region_id']);

        return $row ? new Market('structure', (int) $row->structure_id, $row->name, $row->solar_system_id ? (int) $row->solar_system_id : null, $row->region_id ? (int) $row->region_id : null) : null;
    }

    /** @return Market[] the five trade hubs */
    public static function hubs(): array
    {
        return array_values(array_filter(array_map([self::class, 'station'], Market::HUBS)));
    }

    /** @return Market[] stations and structures whose name contains $query (at most 10 each) */
    public static function search(string $query): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($query)) . '%';
        $out = [];
        foreach (DB::table('staStations')->where('stationName', 'like', $like)->orderBy('stationName')->limit(10)->pluck('stationID') as $id) {
            $out[] = self::station((int) $id);
        }
        foreach (DB::table('universe_structures')->where('name', 'like', $like)->orderBy('name')->limit(10)->pluck('structure_id') as $id) {
            $out[] = self::structure((int) $id);
        }

        return array_values(array_filter($out));
    }

    /**
     * Where the given characters are docked, with the names of the characters at each place.
     *
     * @param  array<int, string>  $characters  names by character ID
     * @return array<int, array{market: Market, characters: string[], character_ids: int[]}>
     */
    public static function docked(array $characters): array
    {
        $places = [];
        $rows = DB::table('character_locations')->whereIn('character_id', array_keys($characters))
            ->get(['character_id', 'station_id', 'structure_id']);
        foreach ($rows as $row) {
            $market = $row->structure_id ? self::structure((int) $row->structure_id)
                : ($row->station_id ? self::station((int) $row->station_id) : null);
            if ($market === null) {
                continue;
            }
            $places[$market->id()] ??= ['market' => $market, 'characters' => [], 'character_ids' => []];
            $places[$market->id()]['characters'][] = $characters[$row->character_id] ?? (string) $row->character_id;
            $places[$market->id()]['character_ids'][] = (int) $row->character_id;
        }

        return array_values($places);
    }
}
