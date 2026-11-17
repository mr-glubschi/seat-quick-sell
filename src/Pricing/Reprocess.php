<?php

namespace Glubschi\Seat\QuickSell\Pricing;

/**
 * Reprocessing yield and output, after the EVE University wiki ("Reprocessing") and the skill descriptions in
 * the SDE:
 *
 * - Ore, ice and moon ore (types with the reprocessingSkillType attribute):
 *   base x (1 + 3% x Reprocessing) x (1 + 2% x Reprocessing Efficiency) x (1 + 2% x the ore's own skill)
 * - Everything else (modules, ships, charges, ...): base x (1 + 2% x Scrapmetal Processing)
 *
 * The base is the facility's yield: 50% in most NPC stations and in Upwell structures; in structures rigs,
 * security status and structure type raise it for ore. It cannot be read from ESI, so the user enters it, one
 * value for ore and one for everything else. Implants are not counted.
 *
 * Items are reprocessed in whole portions (portionSize, e.g. 100 for ore); what is left over stays an item.
 * The facility's tax takes its share of the output.
 */
final class Reprocess
{
    public const REPROCESSING = 3385;

    public const REPROCESSING_EFFICIENCY = 3389;

    public const SCRAPMETAL_PROCESSING = 12196;

    /** dgmTypeAttributes: the skill that improves reprocessing of this ore ("reprocessingSkillType"). */
    public const ATTRIBUTE_ORE_SKILL = 790;

    /**
     * Yield as a fraction (0.55 = 55%).
     *
     * @param  int|null  $oreSkillId  the ore's own processing skill, null for anything that is not ore or ice
     * @param  array<int, int>  $skills  skill levels by skill ID
     */
    public static function yield(?int $oreSkillId, float $orePct, float $scrapPct, array $skills): float
    {
        $level = fn (int $id) => (int) ($skills[$id] ?? 0);
        if ($oreSkillId === null) {
            return $scrapPct / 100 * (1 + 0.02 * $level(self::SCRAPMETAL_PROCESSING));
        }

        return $orePct / 100
            * (1 + 0.03 * $level(self::REPROCESSING))
            * (1 + 0.02 * $level(self::REPROCESSING_EFFICIENCY))
            * (1 + 0.02 * $level($oreSkillId));
    }

    /**
     * Materials received for a stack.
     *
     * @param  array<int, int>  $materials  material quantities per portion, by material type ID
     * @return array{portions: int, materials: array<int, int>}
     */
    public static function output(int $quantity, int $portionSize, array $materials, float $yield, float $taxPct): array
    {
        $portions = intdiv($quantity, max(1, $portionSize));
        $out = [];
        foreach ($materials as $materialId => $perPortion) {
            $received = (int) floor($portions * $perPortion * $yield * (1 - $taxPct / 100) + 1e-9);
            if ($received > 0) {
                $out[$materialId] = $received;
            }
        }

        return ['portions' => $portions, 'materials' => $out];
    }
}
