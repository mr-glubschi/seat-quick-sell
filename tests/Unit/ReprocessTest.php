<?php

namespace Glubschi\Seat\QuickSell\Tests\Unit;

use Glubschi\Seat\QuickSell\Pricing\Reprocess;
use PHPUnit\Framework\TestCase;

class ReprocessTest extends TestCase
{
    private const SIMPLE_ORE_PROCESSING = 60377;

    public function test_scrap_yield(): void
    {
        $this->assertEqualsWithDelta(0.5, Reprocess::yield(null, 50, 50, []), 1e-12);
        // EVE University: 55% with Scrapmetal Processing V at a 50% facility
        $this->assertEqualsWithDelta(0.55, Reprocess::yield(null, 50, 50, [Reprocess::SCRAPMETAL_PROCESSING => 5]), 1e-12);
    }

    public function test_ore_yield(): void
    {
        $skills = [Reprocess::REPROCESSING => 5, Reprocess::REPROCESSING_EFFICIENCY => 5, self::SIMPLE_ORE_PROCESSING => 5];
        // 50% x 1.15 x 1.10 x 1.10 = 69.575%
        $this->assertEqualsWithDelta(0.69575, Reprocess::yield(self::SIMPLE_ORE_PROCESSING, 50, 40, $skills), 1e-12);
        // Scrapmetal Processing does nothing for ore
        $this->assertEqualsWithDelta(0.5, Reprocess::yield(self::SIMPLE_ORE_PROCESSING, 50, 40, [Reprocess::SCRAPMETAL_PROCESSING => 5]), 1e-12);
    }

    public function test_output_in_whole_portions_with_tax(): void
    {
        // 250 Veldspar = 2 portions of 100 (50 left over); 400 Tritanium per portion
        $r = Reprocess::output(250, 100, [34 => 400], 0.5, 10);

        $this->assertSame(2, $r['portions']);
        $this->assertSame([34 => 360], $r['materials']);
    }

    public function test_less_than_a_portion_gives_nothing(): void
    {
        $this->assertSame(['portions' => 0, 'materials' => []], Reprocess::output(99, 100, [34 => 400], 0.7, 0));
    }
}
