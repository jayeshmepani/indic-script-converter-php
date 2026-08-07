# PHP port verification

## Source basis

The PHP port was built against the supplied current Dart/Python implementation, including:

- `transliteration_core`
- Latin/IAST → Devanagari
- Latin/IAST → Gujarati
- Latin/IAST → plain English / Hunterian
- Devanagari/Gujarati → canonical IAST
- lossless envelope + exact-source metadata
- direct Devanagari ↔ Gujarati conversion
- aligned 497-case corpora
- 22 Vedic fixtures

## Executed runtime

```text
PHP 8.4.16 CLI
```

The package itself declares and uses syntax compatible with PHP 8.3+.

## Exact original-direction parity

| Direction | Cases | Exact matches |
|---|---:|---:|
| Latin/IAST → Devanagari | 497 | 497 |
| Latin/IAST → Gujarati | 497 | 497 |
| Latin/IAST → plain English | 497 | 497 |
| Devanagari → canonical IAST | 497 | 497 |
| Gujarati → canonical IAST | 497 | 497 |
| **Original five-direction total** | **2,485** | **2,485** |

All **22/22** supplied Vedic Devanagari fixtures match exactly.

## Direct script parity

The PHP direct converter was compared against the existing direct JavaScript/Python implementation:

| Direction | Cases | Exact matches |
|---|---:|---:|
| Devanagari → Gujarati | 497 | 497 |
| Gujarati → Devanagari | 497 | 497 |
| **Direct total** | **994** | **994** |

Exact metadata-backed script recovery was additionally tested over all 497 Devanagari and all 497 Gujarati corpus entries.

## Exact-source metadata

Verified:

- exact Latin source recovery from Devanagari
- exact Latin source recovery from Gujarati
- exact Devanagari source recovery through Gujarati
- exact Gujarati source recovery through Devanagari
- source checksum validation
- visible-rendering checksum validation
- typed direct-script source markers
- rejection of unrelated Latin-source metadata by strict direct-script recovery
- NFC/NFD and combining-order preservation
- supplementary code points
- byte-for-byte compatibility with the supplied Dart/Python/JavaScript UTF-16 metadata vector, including an isolated UTF-16 surrogate unit

## Unicode implementation

The PHP runtime has no dependency on `intl` or `mbstring`. Canonical NFD/NFC, canonical combining classes, Unicode mark categories, and simple case maps are bundled from the official **Unicode Character Database 17.0.0**. Hangul canonical decomposition/composition is implemented algorithmically.

## Test result

```text
29 tests passed
7,585 assertions passed
```

All PHP source, tool, example, and test files also pass `php -l` syntax validation.
