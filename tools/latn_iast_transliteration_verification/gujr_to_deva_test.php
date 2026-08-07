<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_gujr.php';
require_once __DIR__ . '/_common.php';

use function IndicScriptConverter\toCanonicalDevanagariFromGujarati;
use function IndicScriptConverter\Verification\jsonText;

use const IndicScriptConverter\Verification\GUJARATI_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' GUJARATI TO DEVANAGARI SCRIPT CONVERSION', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

foreach (GUJARATI_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toCanonicalDevanagariFromGujarati($source)), PHP_EOL;
}
