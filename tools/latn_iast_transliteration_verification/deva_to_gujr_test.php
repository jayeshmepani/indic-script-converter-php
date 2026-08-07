<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_deva.php';
require_once __DIR__ . '/_common.php';

use function IndicScriptConverter\toCanonicalGujaratiFromDevanagari;
use function IndicScriptConverter\Verification\jsonText;

use const IndicScriptConverter\Verification\DEVANAGARI_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' DEVANAGARI TO GUJARATI SCRIPT CONVERSION', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

foreach (DEVANAGARI_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toCanonicalGujaratiFromDevanagari($source)), PHP_EOL;
}
