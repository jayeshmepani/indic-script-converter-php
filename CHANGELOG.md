# Changelog

## 1.1.0

- Added direct Devanagari/Gujarati → Plain-English and Hunterian APIs, bulk array helpers, and result-envelope variants.
- Added source-aware inherent-schwa tracking with Gujarati/Hindi/Sanskrit policies.
- Added direct-transcription controls for vowel length and terminal anusvara.
- Kept legacy IAST → Plain-English/Hunterian behavior backward-compatible.
- Added Brahmic naturalization regression tests.
- Hardened Unicode 18 Devanagari/Gujarati reverse coverage for historic prishthamatra sequences, short-AI AY, Devanagari Extended/Extended-A, Vedic segmental/nasal signs, and Common Indic number forms.
- Added semantic Gujarati Arabic-transliteration handling for sukun, shadda, maddah, special nuktas, and configurable `ʿayn` rendering.
- Corrected Sindhi implosive identities (`ɠ`, `ʄ`, `ɗ`, `ɓ`) and added canonical forward aliases where applicable.
- Plain-English/Hunterian now renders semantic OM as `om` and no longer leaks specialized Vedic/Devanagari nasal marks by default.
- Fixed historic long-E/OOE readable rendering (`ॕ → ee`, `ऻ/ॴ → ooe`) consistently across runtimes.
- Preserved pre-existing Latin/IAST text, Unicode diacritics, and smart apostrophes/quotes exactly in direct Brahmic → Plain-English/Hunterian mixed-script input.
- Added Konkani `च़ → ċ → tsa`, audible candrabindu handling, dangling-modifier cleanup, and Gujarati Hunterian visarga handling without rewriting original Latin `w`.

## 1.0.2

- Added bulk array transliteration functions (`toDevanagariFromIastList`, `toGujaratiFromIastList`, `toPlainEnglishFromIastList`, `toCanonicalGujaratiFromDevanagariList`, `toCanonicalDevanagariFromGujaratiList`, `toExactDevanagariFromGujaratiList`, `toExactGujaratiFromDevanagariList`, `toDevanagariList`, `toGujaratiList`, `toPlainEnglishList`).

## 1.0.1

- Synchronized package version across all runtimes to 1.0.1.
- Resolved function name redeclaration error in `BrahmicToLatnIast.php` by using `hasExactDevanagariIastSourceMetadata` and `hasExactGujaratiIastSourceMetadata`.
- Added top-level metadata helpers (`hasExactGujaratiSourceMetadata`, `hasExactDevanagariSourceMetadata`, `visibleWithoutExactSourceMetadata`).
- Added exhaustive public API example runner exercising all 35 core functions, options, and enums.

## 1.0.0

- Added canonical Devanagari/Gujarati script-to-script conversion options and policies.
- Preserved Vedic mark reattachment on canonical script transliterations.
- Standardized package manifests, author details, and release actions across all runtimes.
- Added the complete Node.js 20+/ES2023 port.
- Preserved `extendedIndic` as the default profile.
- Ported Devanagari and Gujarati forward conversion mappings and policies.
- Ported plain-English and Hunterian transcription policies.
- Ported canonical Devanagari/Gujarati reverse conversion.
- Ported Vedic accent handling and accent reattachment.
- Ported Unicode normalization controls.
- Ported the exact round-trip result envelope and JSON schema.
- Ported the invisible exact-source Unicode Tag trailer byte-for-byte.
- Added all 497 index-aligned corpus cases in five conversion directions.
- Added all 22 Vedic fixtures.
- Added five CLI-compatible output generators.
- Added Node built-in test-runner coverage with no runtime dependencies.
