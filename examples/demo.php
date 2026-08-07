<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use IndicScriptConverter\IastToDevanagariOptions;
use IndicScriptConverter\IastToGujaratiOptions;
use IndicScriptConverter\IndicScriptConversionOptions;

use function IndicScriptConverter\toCanonicalDevanagariFromGujarati;
use function IndicScriptConverter\toCanonicalGujaratiFromDevanagari;
use function IndicScriptConverter\toDevanagariFromIast;
use function IndicScriptConverter\toExactDevanagariFromGujarati;
use function IndicScriptConverter\toExactIastFromDevanagari;
use function IndicScriptConverter\toExactIastFromGujarati;
use function IndicScriptConverter\toGujaratiFromIast;
use function IndicScriptConverter\toPlainEnglishFromIast;

$source = 'Kṛṣṇa / Kr̥ṣṇa / ḫāna / ṣ́akti';

echo 'Latin/IAST:  ', $source, PHP_EOL;
echo 'Devanagari:  ', toDevanagariFromIast($source), PHP_EOL;
echo 'Gujarati:    ', toGujaratiFromIast($source), PHP_EOL;
echo 'Plain:       ', toPlainEnglishFromIast($source), PHP_EOL;

$taggedDevanagari = toDevanagariFromIast(
    $source,
    new IastToDevanagariOptions(embedExactSourceMetadata: true),
);
$taggedGujarati = toGujaratiFromIast(
    $source,
    new IastToGujaratiOptions(embedExactSourceMetadata: true),
);

echo 'Exact ← Deva: ', toExactIastFromDevanagari($taggedDevanagari), PHP_EOL;
echo 'Exact ← Gujr: ', toExactIastFromGujarati($taggedGujarati), PHP_EOL;

echo 'Deva → Gujr: ', toCanonicalGujaratiFromDevanagari('कृष्ण वसोः॑'), PHP_EOL;
echo 'Gujr → Deva: ', toCanonicalDevanagariFromGujarati('કૃષ્ણ વસોઃ॑'), PHP_EOL;

$exactScript = toCanonicalGujaratiFromDevanagari(
    'ऄ ऎ ऍ ॲ ऒ ऑ ॵ ळ ऴ ग़ ॻ ड़ ॸ ॾ',
    new IndicScriptConversionOptions(embedExactSourceMetadata: true),
);
echo 'Exact script reverse: ', toExactDevanagariFromGujarati($exactScript), PHP_EOL;
