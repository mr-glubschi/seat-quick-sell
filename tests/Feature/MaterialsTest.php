<?php

namespace Glubschi\Seat\QuickSell\Tests\Feature;

use Glubschi\Seat\QuickSell\Items\Materials;
use Glubschi\Seat\QuickSell\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Materials against small copies of SeAT's SDE tables in SQLite. */
class MaterialsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('invTypes', function ($t) {
            $t->integer('typeID')->primary();
            $t->string('typeName');
            $t->integer('portionSize');
        });
        Schema::create('invTypeMaterials', function ($t) {
            $t->integer('typeID');
            $t->integer('materialTypeID');
            $t->integer('quantity');
        });
        Schema::create('dgmTypeAttributes', function ($t) {
            $t->integer('typeID');
            $t->integer('attributeID');
            $t->integer('valueInt')->nullable();
            $t->float('valueFloat')->nullable();
        });
        DB::table('invTypes')->insert([
            ['typeID' => 1230, 'typeName' => 'Veldspar', 'portionSize' => 100],
            ['typeID' => 2488, 'typeName' => 'Warrior II', 'portionSize' => 1],
            ['typeID' => 34, 'typeName' => 'Tritanium', 'portionSize' => 1],
        ]);
        DB::table('invTypeMaterials')->insert([
            ['typeID' => 1230, 'materialTypeID' => 34, 'quantity' => 400],
            ['typeID' => 2488, 'materialTypeID' => 36, 'quantity' => 30],
            ['typeID' => 2488, 'materialTypeID' => 34, 'quantity' => 120],
        ]);
        DB::table('dgmTypeAttributes')->insert([
            ['typeID' => 1230, 'attributeID' => 790, 'valueInt' => null, 'valueFloat' => 60377],
            ['typeID' => 1230, 'attributeID' => 1980, 'valueInt' => null, 'valueFloat' => 0.5],
        ]);
    }

    public function test_reads_materials_portions_and_ore_skill(): void
    {
        $m = Materials::for([1230, 2488, 34]);

        $this->assertSame(['portion' => 100, 'ore_skill' => 60377, 'materials' => [34 => 400]], $m[1230]);
        $this->assertSame(['portion' => 1, 'ore_skill' => null, 'materials' => [34 => 120, 36 => 30]], $m[2488]);
        $this->assertArrayNotHasKey(34, $m);
        $this->assertEqualsCanonicalizing([34, 36], Materials::materialIds($m));
    }
}
