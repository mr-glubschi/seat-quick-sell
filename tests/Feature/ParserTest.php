<?php

namespace Glubschi\Seat\QuickSell\Tests\Feature;

use Glubschi\Seat\QuickSell\Items\Parser;
use Glubschi\Seat\QuickSell\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Parser against a small copy of SeAT's SDE table invTypes in SQLite. */
class ParserTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('invTypes', function ($t) {
            $t->integer('typeID')->primary();
            $t->string('typeName');
            $t->boolean('published');
        });
        DB::table('invTypes')->insert([
            ['typeID' => 34, 'typeName' => 'Tritanium', 'published' => 1],
            ['typeID' => 35, 'typeName' => 'Pyerite', 'published' => 1],
            ['typeID' => 12058, 'typeName' => '10MN Afterburner II', 'published' => 1],
            ['typeID' => 99, 'typeName' => 'Pyerite', 'published' => 0],
        ]);
    }

    public function test_inventory_copy(): void
    {
        $text = "Tritanium\t1,000\tMineral\t\t\t10 m3\nPyerite\t2.500\tMineral\n10MN Afterburner II\t\tPropulsion Module";
        $r = Parser::parse($text);

        $this->assertSame([], $r['unknown']);
        $this->assertSame([
            ['type_id' => 34, 'name' => 'Tritanium', 'quantity' => 1000],
            ['type_id' => 35, 'name' => 'Pyerite', 'quantity' => 2500],
            ['type_id' => 12058, 'name' => '10MN Afterburner II', 'quantity' => 1],
        ], $r['items']);
    }

    public function test_lists_and_stacks(): void
    {
        $r = Parser::parse("1000 Tritanium\nTritanium x500\n3 x 10MN Afterburner II\nPyerite\nNo Such Item 5");

        $byId = array_column($r['items'], 'quantity', 'type_id');
        $this->assertSame(1500, $byId[34]);
        $this->assertSame(3, $byId[12058]);
        $this->assertSame(1, $byId[35]);
        $this->assertSame(['No Such Item 5'], $r['unknown']);
    }

    public function test_quantity_separators(): void
    {
        $this->assertSame(1000000, Parser::quantity("1'000'000"));
        $this->assertSame(1000, Parser::quantity('1 000'));
        $this->assertNull(Parser::quantity('0'));
        $this->assertNull(Parser::quantity('abc'));
    }
}
