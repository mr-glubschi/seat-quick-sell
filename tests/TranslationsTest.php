<?php

namespace Glubschi\Seat\QuickSell\Tests;

use PHPUnit\Framework\TestCase;

/** Every language must have the same files and keys as English, so no screen shows a raw key. */
class TranslationsTest extends TestCase
{
    private const LANG = __DIR__ . '/../src/resources/lang';

    public function test_all_languages_have_the_english_keys(): void
    {
        $files = array_map('basename', glob(self::LANG . '/en/*.php'));
        $this->assertNotEmpty($files);

        foreach (glob(self::LANG . '/*', GLOB_ONLYDIR) as $dir) {
            $lang = basename($dir);
            $this->assertSame($files, array_map('basename', glob("$dir/*.php")), "files of $lang");

            foreach ($files as $file) {
                $en = array_keys(require self::LANG . "/en/$file");
                $other = array_keys(require "$dir/$file");
                sort($en);
                sort($other);
                $this->assertSame($en, $other, "keys of $lang/$file");
            }
        }
    }
}
