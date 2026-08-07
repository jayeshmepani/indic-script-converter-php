<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_latn_iast.php';
require_once __DIR__ . '/_common.php';

use function IndicScriptConverter\toPlainEnglishFromIast;
use function IndicScriptConverter\Verification\jsonText;

use const IndicScriptConverter\Verification\TRANSLITERATION_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' 3. PLAIN ENGLISH TRANSLITERATOR SAMPLES', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

foreach (TRANSLITERATION_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toPlainEnglishFromIast($source)), PHP_EOL;
}
