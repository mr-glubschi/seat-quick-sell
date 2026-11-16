<?php

namespace Glubschi\Seat\QuickSell\Items;

use Illuminate\Support\Facades\DB;

/**
 * Reads items copied from the EVE client.
 *
 * Quick Sell is used with the inventory: select items, Ctrl+C, paste. The client copies one item per line,
 * tab-separated, name first and quantity second ("Tritanium\t1,000\tMineral\t..."); an assembled single item
 * has an empty quantity. Lists with the quantity first ("1000 Tritanium", "10 x Tritanium") or last
 * ("Tritanium 1000", "Tritanium x10") and bare names are understood too.
 *
 * Names are matched exactly (case ignored) against SeAT's SDE table invTypes, which holds English names only.
 * Several stacks of the same item are added up. A line that matches no item is reported, never guessed.
 */
final class Parser
{
    public const MAX_LINES = 2000;

    /**
     * @return array{items: array<int, array{type_id: int, name: string, quantity: int}>, unknown: array<int, string>}
     */
    public static function parse(string $text): array
    {
        $lines = array_slice(preg_split('/\r\n|\r|\n/', $text), 0, self::MAX_LINES);

        // All readings of all lines, then one query for every name that could be meant.
        $readings = [];
        $names = [];
        foreach ($lines as $index => $raw) {
            $line = trim($raw);
            if ($line === '') {
                continue;
            }
            $readings[$index] = self::candidates($line);
            foreach ($readings[$index] as [$name]) {
                $names[mb_strtolower($name)] = $name;
            }
        }
        $types = self::lookup(array_values($names));

        $items = [];
        $unknown = [];
        foreach ($readings as $index => $candidates) {
            $found = false;
            foreach ($candidates as [$name, $quantity]) {
                $type = $types[mb_strtolower($name)] ?? null;
                if ($type !== null) {
                    $id = (int) $type->typeID;
                    $items[$id] ??= ['type_id' => $id, 'name' => $type->typeName, 'quantity' => 0];
                    $items[$id]['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $unknown[] = mb_substr(trim($lines[$index]), 0, 200);
            }
        }

        return ['items' => array_values($items), 'unknown' => $unknown];
    }

    /** A quantity with the thousands separators of the client languages (1,000 1.000 1'000 1 000). */
    public static function quantity(string $raw): ?int
    {
        $digits = preg_replace("/[,.'\x{00A0}\x{202F} ]/u", '', $raw);
        if (! preg_match('/^\d{1,15}$/', $digits)) {
            return null;
        }
        $n = (int) $digits;

        return $n > 0 ? $n : null;
    }

    /**
     * Possible (name, quantity) readings of one line, most likely first.
     *
     * @return array<int, array{0: string, 1: int}>
     */
    public static function candidates(string $line): array
    {
        // Some windows mark items with a trailing "*".
        $clean = static fn (string $name) => preg_replace('/\s*\*$/', '', trim($name));
        $out = [];

        if (str_contains($line, "\t")) {
            $cols = array_map('trim', explode("\t", $line));
            // Inventory: name, quantity (empty = one assembled item), ...
            $q = ($cols[1] ?? '') === '' ? 1 : self::quantity($cols[1]);
            if ($q !== null) {
                $out[] = [$clean($cols[0]), $q];
            }
            // quantity, name
            $q0 = self::quantity($cols[0]);
            if ($q0 !== null && ($cols[1] ?? '') !== '') {
                $out[] = [$clean($cols[1]), $q0];
            }
            $out[] = [$clean($cols[0]), 1];

            return $out;
        }

        // Quantity first: "1000 Tritanium", "1.000 x Tritanium", "10x Tritanium".
        if (preg_match("/^([\d,.'\x{00A0} ]*\d)\s*[xX\x{00D7}]?\s+(.+)$/u", $line, $m) && ($q = self::quantity($m[1])) !== null) {
            $out[] = [$clean($m[2]), $q];
        }
        // Quantity last: "Tritanium 1000", "Tritanium x1000", "Tritanium: 1000".
        if (preg_match("/^(.+?):?\s+[xX\x{00D7}]?\s*(\d[\d,.'\x{00A0} ]*)$/u", $line, $m) && ($q = self::quantity($m[2])) !== null) {
            $out[] = [$clean($m[1]), $q];
        }
        $out[] = [$clean($line), 1];

        return $out;
    }

    /**
     * Types by lower-case name. Published types win over unpublished ones with the same name.
     *
     * @param  string[]  $names
     * @return array<string, object>
     */
    private static function lookup(array $names): array
    {
        $found = [];
        foreach (array_chunk($names, 500) as $chunk) {
            $rows = DB::table('invTypes')
                ->select('typeID', 'typeName', 'published')
                ->whereIn('typeName', $chunk)
                ->orderBy('published')
                ->get();
            foreach ($rows as $row) {
                // Later rows (published) overwrite earlier ones (unpublished).
                $found[mb_strtolower($row->typeName)] = $row;
            }
        }

        return $found;
    }
}
