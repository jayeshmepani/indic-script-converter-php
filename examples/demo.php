<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use Lipimala\IastToDevanagariOptions;
use Lipimala\IastToGujaratiOptions;
use Lipimala\IndicScriptConversionOptions;

use function Lipimala\toCanonicalDevanagariFromGujarati;
use function Lipimala\toCanonicalGujaratiFromDevanagari;
use function Lipimala\toDevanagariFromIast;
use function Lipimala\toExactDevanagariFromGujarati;
use function Lipimala\toExactIastFromDevanagari;
use function Lipimala\toExactIastFromGujarati;
use function Lipimala\toGujaratiFromIast;
use function Lipimala\toPlainEnglishFromIast;

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
