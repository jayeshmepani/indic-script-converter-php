# lipimala — PHP

A PHP **8.3+** port of the supplied Dart/Python Indic-script conversion libraries. It preserves the same conversion tables, profile defaults, contextual rules, Vedic handling, canonical reverse conversion, exact round-trip result envelope, and checksummed exact-source metadata format. The direct Gujarati ↔ Devanagari converter is included as well.

## Runtime

- PHP 8.3 or newer
- `declare(strict_types=1)` throughout
- no runtime package dependencies
- no `intl`/`mbstring` requirement
- bundled Unicode 17.0.0 canonical normalization and mark data

The source project uses `extendedIndic` as the default forward profile, and this PHP port keeps that default.

## Included conversions

- Latin/IAST + extended Indic → Devanagari
- Latin/IAST + extended Indic → Gujarati
- Latin/IAST → plain English
- explicit Hunterian view
- Devanagari → canonical IAST
- Gujarati → canonical IAST
- Devanagari → Gujarati
- Gujarati → Devanagari
- metadata-backed exact source recovery
- exact round-trip JSON envelope serialization

## Installation

### Via Composer (recommended)

```bash
composer require jayeshmepani/lipimala
```

### Manual (no Composer)

```php
require '/path/to/lipimala-php/autoload.php';
```

### Requirements

- PHP **8.3** or newer
- No runtime dependencies — no `intl`, no `mbstring`

## Basic API

```php
<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use function IndicScriptConverter\toCanonicalIastFromDevanagari;
use function IndicScriptConverter\toCanonicalIastFromGujarati;
use function IndicScriptConverter\toDevanagariFromIast;
use function IndicScriptConverter\toGujaratiFromIast;
use function IndicScriptConverter\toPlainEnglishFromIast;

echo toDevanagariFromIast('Kṛṣṇa');             // कृष्ण
echo toGujaratiFromIast('Kṛṣṇa');               // કૃષ્ણ
echo toPlainEnglishFromIast('Kṛṣṇa');           // Krishna
echo toCanonicalIastFromDevanagari('कृष्ण');    // kṛṣṇa
echo toCanonicalIastFromGujarati('કૃષ્ણ');      // kṛṣṇa
```

## Forward profiles

The default is `extendedIndic`:

```php
use IndicScriptConverter\DevanagariRomanizationProfile;
use IndicScriptConverter\IastToDevanagariOptions;

$options = new IastToDevanagariOptions(
    profile: DevanagariRomanizationProfile::EXTENDED_INDIC,
);
```

The forward script APIs expose the same policy families as the source implementation:

- `strictIast`
- `iso15919Core`
- `extendedIndic`
- unknown Latin handling
- digit conversion/preservation
- punctuation preservation/Indic danda
- OM letter/sign policy
- ambiguous `ḷ` handling
- ASCII long-vowel aliases
- `sh`, `x`, `w` compatibility switches
- Vedic accent preservation
- whitespace collapse
- exact-source metadata embedding

## Exact Latin-key recovery

Visible Brahmic output is necessarily many-to-one. Case, source normalization, aliases, and equivalent extended spellings can collapse to the same visible script. Exact recovery therefore uses the same invisible Unicode-Tag trailer as the Dart/Python/JavaScript implementations.

```php
use IndicScriptConverter\IastToDevanagariOptions;
use function IndicScriptConverter\toDevanagariFromIast;
use function IndicScriptConverter\toExactIastFromDevanagari;

$source = 'Kṛṣṇa / Kr̥ṣṇa / ḫāna / ṣ́akti';

$tagged = toDevanagariFromIast(
    $source,
    new IastToDevanagariOptions(
        embedExactSourceMetadata: true,
    ),
);

assert(toExactIastFromDevanagari($tagged) === $source);
```

Gujarati uses `IastToGujaratiOptions` and `toExactIastFromGujarati()`.

The metadata encodes the exact UTF-16LE source unit sequence, stores independent FNV-1a checksums for source and visible rendering, and rejects corrupted/tampered trailers. The PHP implementation is byte-for-byte compatible with the supplied cross-language metadata vector, including supplementary code points and isolated UTF-16 surrogate units.

## Canonical reverse vs exact reverse

```text
toIastFromDevanagari()          exact metadata when present, canonical otherwise
toExactIastFromDevanagari()     metadata required
toCanonicalIastFromDevanagari() always canonical visible reverse

toIastFromGujarati()            exact metadata when present, canonical otherwise
toExactIastFromGujarati()       metadata required
toCanonicalIastFromGujarati()   always canonical visible reverse
```

Canonical reverse cannot infer which Latin alias/case/normalization originally produced a visible script string.

## Direct Gujarati ↔ Devanagari

Canonical visible conversion:

```php
use function IndicScriptConverter\toCanonicalDevanagariFromGujarati;
use function IndicScriptConverter\toCanonicalGujaratiFromDevanagari;

echo toCanonicalGujaratiFromDevanagari('कृष्ण'); // કૃષ્ણ
echo toCanonicalDevanagariFromGujarati('કૃષ્ણ'); // कृष्ण
```

The two scripts have unequal Unicode repertoires, so visible conversion is non-injective. Exact script-source recovery uses a typed source marker inside the same metadata trailer:

```php
use IndicScriptConverter\IndicScriptConversionOptions;
use function IndicScriptConverter\toCanonicalGujaratiFromDevanagari;
use function IndicScriptConverter\toExactDevanagariFromGujarati;

$source = 'ऄ ऎ ऍ ॲ ऒ ऑ ॵ ळ ऴ ग़ ॻ ड़ ॸ ॾ';

$taggedGujarati = toCanonicalGujaratiFromDevanagari(
    $source,
    new IndicScriptConversionOptions(
        embedExactSourceMetadata: true,
    ),
);

assert(toExactDevanagariFromGujarati($taggedGujarati) === $source);
```

The opposite direction uses `toCanonicalDevanagariFromGujarati()` and `toExactGujaratiFromDevanagari()`.

Direct conversion also exposes source-digit preservation, target-digit conversion, unknown-character preservation/strict error, normalization, whitespace collapse, and exact round-trip-envelope APIs.

## Vedic ordering

The forward implementation keeps the source library's Unicode-recommended Brahmic storage order: vowel/matra, then bindu/visarga, then svara. Forms such as `वसोः॑` and `जुष्टं॑` therefore remain unchanged from the verified Dart behavior.

## Exact Round-Trip envelope

```php
use function IndicScriptConverter\toDevanagari;

$result = toDevanagari('Kṛṣṇa');
$json = $result->toJsonText();
$original = $result->restoreOriginal();
```

The envelope schema is `exact round-trip-indic-transliteration/1` and includes original code-point integrity checking.

## CLI output generators

Run from the PHP project root:

```bash
php tools/latn_iast_transliteration_verification/latn_iast_to_deva_test.php > latn_iast_to_deva_output.txt
php tools/latn_iast_transliteration_verification/latn_iast_to_gujr_test.php > latn_iast_to_gujr_output.txt
php tools/latn_iast_transliteration_verification/latn_iast_transcription_test.php > latn_iast_transcription_output.txt
php tools/latn_iast_transliteration_verification/deva_to_latn_iast_test.php > deva_to_latn_iast_output.txt
php tools/latn_iast_transliteration_verification/gujr_to_latn_iast_test.php > gujr_to_latn_iast_output.txt
php tools/latn_iast_transliteration_verification/deva_to_gujr_test.php > deva_to_gujr_output.txt
php tools/latn_iast_transliteration_verification/gujr_to_deva_test.php > gujr_to_deva_output.txt
```

All runners JSON-escape each source/result string so quotes and embedded newlines cannot corrupt record boundaries.

Run every output generator:

```bash
php tools/latn_iast_transliteration_verification/run_all.php
```

## Verification

```bash
php tests/run.php
```

The bundled regression suite verifies the five original 497-case directions, both 497-case direct script directions, all 22 Vedic fixtures, profile/option behavior, exact metadata, tamper rejection, exact round-trip JSON envelopes, and exact direct-script corpus recovery.

See `PORT_VERIFICATION.md` for the executed result and parity counts.
