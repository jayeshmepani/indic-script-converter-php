<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_latn_iast.php';
require_once __DIR__ . '/_common.php';

use IndicScriptConverter\IastToDevanagariDigitPolicy;
use IndicScriptConverter\IastToDevanagariOptions;
use IndicScriptConverter\IastToDevanagariPunctuationPolicy;

use function IndicScriptConverter\toDevanagariFromIast;
use function IndicScriptConverter\Verification\jsonText;

use const IndicScriptConverter\Verification\TRANSLITERATION_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' 1. DEVANAGARI TRANSLITERATOR SAMPLES', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

$options = new IastToDevanagariOptions(
    digitPolicy: IastToDevanagariDigitPolicy::CONVERT_TO_SCRIPT,
    punctuationPolicy: IastToDevanagariPunctuationPolicy::INDIC_DANDA,
);

foreach (TRANSLITERATION_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toDevanagariFromIast($source, $options)), PHP_EOL;
}
