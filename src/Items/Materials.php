<?php

namespace Glubschi\Seat\QuickSell\Items;

use Glubschi\Seat\QuickSell\Pricing\Reprocess;
use Illuminate\Support\Facades\DB;

/**
 * What items reprocess into, from SeAT's SDE tables: invTypeMaterials (materials per portion), invTypes
 * (portionSize) and dgmTypeAttributes (the ore's own processing skill). All three are among the tables SeAT
 * imports by default, so nothing is added to the SDE import.
 */
final class Materials
{
    /**
     * @param  int[]  $typeIds
     * @return array<int, array{portion: int, ore_skill: int|null, materials: array<int, int>}>  by type ID;
     *                                                                                       items without materials are left out
     */
    public static function for(array $typeIds): array
    {
        if ($typeIds === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('invTypeMaterials')->whereIn('typeID', $typeIds)->get(['typeID', 'materialTypeID', 'quantity']) as $row) {
            $out[(int) $row->typeID]['materials'][(int) $row->materialTypeID] = (int) $row->quantity;
        }
        if ($out === []) {
            return [];
        }
        $ids = array_keys($out);
        $portions = DB::table('invTypes')->whereIn('typeID', $ids)->pluck('portionSize', 'typeID');
        $skills = DB::table('dgmTypeAttributes')->whereIn('typeID', $ids)
            ->where('attributeID', Reprocess::ATTRIBUTE_ORE_SKILL)
            ->get(['typeID', 'valueInt', 'valueFloat']);
        $oreSkill = [];
        foreach ($skills as $row) {
            $oreSkill[(int) $row->typeID] = (int) ($row->valueInt ?? $row->valueFloat);
        }
        $result = [];
        foreach ($out as $typeId => $info) {
            ksort($info['materials']);
            $result[$typeId] = [
                'portion' => max(1, (int) ($portions[$typeId] ?? 1)),
                'ore_skill' => ($oreSkill[$typeId] ?? 0) ?: null,
                'materials' => $info['materials'],
            ];
        }

        return $result;
    }

    /** @return int[] every material the given items reprocess into */
    public static function materialIds(array $reprocessing): array
    {
        $ids = [];
        foreach ($reprocessing as $info) {
            foreach (array_keys($info['materials']) as $id) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
