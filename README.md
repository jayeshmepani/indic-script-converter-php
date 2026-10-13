# lipimala for PHP

[![Packagist version](https://img.shields.io/packagist/v/jayeshmepani/lipimala.svg?style=flat-square)](https://packagist.org/packages/jayeshmepani/lipimala)
[![PHP version](https://img.shields.io/packagist/php-v/jayeshmepani/lipimala?style=flat-square)](https://packagist.org/packages/jayeshmepani/lipimala)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE)

IAST-style transliteration for Devanagari and Gujarati, with canonical reverse conversion, direct script conversion, and Plain-English or Hunterian output.

## Install

```bash
composer require jayeshmepani/lipimala
```

Requires PHP 8.3 or newer. The package has no runtime dependencies and does not require `intl` or `mbstring`.

## Use

```php
<?php

use function Lipimala\toDevanagariFromIast;
use function Lipimala\toCanonicalIastFromGujarati;
use function Lipimala\toPlainEnglishFromDevanagari;
use function Lipimala\toPlainEnglishFromGujarati;
use function Lipimala\toCanonicalGujaratiFromDevanagari;

echo toDevanagariFromIast('Kṛṣṇa'); // कृष्ण
echo toCanonicalIastFromGujarati('કૃષ્ણ'); // kṛṣṇa
echo toPlainEnglishFromDevanagari('कानपुर'); // kaanpur
echo toPlainEnglishFromGujarati('આજ મારે'); // aaj maare
echo toCanonicalGujaratiFromDevanagari('कृष्ण'); // કૃષ્ણ
```

Use `toHunterianFromDevanagari()` and `toHunterianFromGujarati()` for Hunterian output. Array helpers such as `toDevanagariFromIastList()` convert lists of strings.

## Profiles and options

Forward conversion accepts `strictIast`, `iso15919Core`, or `extendedIndic` profiles. The default is `extendedIndic`. Options control normalization, unknown input, digits, punctuation, Vedic accents, whitespace, and exact-source metadata.

Direct Brahmic transcription accepts `BrahmicPlainEnglishOptions` for language selection, schwa deletion, vowel length, terminal anusvara, Gujarati ʿayn, and other pronunciation choices.

## Preserve the original input

The rendered script may not encode the original Latin spelling, capitalization, or normalization. A result envelope keeps the source beside the rendered value:

```php
<?php

use function Lipimala\toDevanagari;

$result = toDevanagari('Kṛṣṇa');
echo $result->rendered;          // कृष्ण
echo $result->restoreOriginal(); // Kṛṣṇa
```

For a standalone string, set `embedExactSourceMetadata: true` in the conversion options and use an exact reverse function such as `toExactIastFromDevanagari()`. Unicode-tag metadata can be removed by systems that sanitize invisible characters.

## License

MIT. See [LICENSE](LICENSE).
