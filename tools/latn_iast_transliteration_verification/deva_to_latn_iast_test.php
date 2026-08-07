<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_deva.php';
require_once __DIR__ . '/_common.php';

use function Lipimala\toCanonicalIastFromDevanagari;
use function Lipimala\Verification\jsonText;

use const Lipimala\Verification\DEVANAGARI_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' DEVANAGARI TO LATN IAST TRANSLITERATION', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

foreach (DEVANAGARI_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toCanonicalIastFromDevanagari($source)), PHP_EOL;
}
