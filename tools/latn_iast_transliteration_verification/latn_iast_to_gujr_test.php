<?php

declare(strict_types=1);

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/example_latn_iast.php';
require_once __DIR__ . '/_common.php';

use IndicScriptConverter\IastToGujaratiDigitPolicy;
use IndicScriptConverter\IastToGujaratiOptions;
use IndicScriptConverter\IastToGujaratiPunctuationPolicy;

use function IndicScriptConverter\toGujaratiFromIast;
use function IndicScriptConverter\Verification\jsonText;

use const IndicScriptConverter\Verification\TRANSLITERATION_SMOKE_SAMPLES;

echo str_repeat('-', 64), PHP_EOL;
echo ' 2. GUJARATI TRANSLITERATOR SAMPLES', PHP_EOL;
echo str_repeat('-', 64), PHP_EOL;

$options = new IastToGujaratiOptions(
    digitPolicy: IastToGujaratiDigitPolicy::CONVERT_TO_SCRIPT,
    punctuationPolicy: IastToGujaratiPunctuationPolicy::INDIC_DANDA,
);

foreach (TRANSLITERATION_SMOKE_SAMPLES as $source) {
    echo jsonText($source), ' -> ', jsonText(toGujaratiFromIast($source, $options)), PHP_EOL;
}
