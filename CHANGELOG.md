# Changelog

## 1.2.0

- Added opt-in Brahmic naturalization for the currently supported Devanagari and Gujarati scripts while keeping all 1.1 defaults unchanged.
- Added a three-way Brahmic vowel-length policy: `COLLAPSE`, `DOUBLE`, and contextual `SMART` (Dart: `collapse`, `doubled`, `smart`).
- `SMART` is syllable spelling for modern Gujarati and Hindi. Closed one-syllable words keep length (`aaj`, `chaar`, `naam`, `hoon`, `swaroop`). Open particles and heavy clusters shorten (`ka`, `marg`, `dharan`, `karine`). An open ending keeps stem length (`swaroopama`, `paasethi`, `shwetdweepthi`). A nasal coda keeps a peripheral vowel (`hoonga`). Sanskrit SMART preserves phonemic length (`shvetadveepa`, `svaroopa`).
- Natural schwa between `p` and unaspirated `t` stays, so Hindi `राष्ट्रपति` is `rashtrapati`. Aspirated `થ` / `थ` still contracts (`શાપથી` → `shaapthi`). Legacy `गांधीनगर` stays `gaandheengar`; natural Hindi is `gandhinagar`.
- Plain-English defaults to `DOUBLE`. Hunterian defaults to `COLLAPSE` when vowel length is omitted. With `NATURAL` + `SMART` on both, Gujarati Plain and Hunterian match. Gujarati, and modern `NATURAL` Hindi, do not apply blanket consonant-plus-`v` → `w`.
- Named Hunterian as simplified ASCII Hunterian. A long vowel is one letter (`jan`) and consonant dots are removed. The 1954 macron form (`jān`) is not emitted.
- Generalized natural schwa/compound handling across the Devanagari/Hindi and Gujarati paths, including `श्वेतद्वीप/શ્વેતદ્વીપ → shwetdweep`, `स्वरूप/સ્વરૂપ → swaroop`, and `सतयुग/સતયુગ → satyug`.
- Added Gujarati ending patterns (`જેટલાં → jetla`, `બ્રહ્માંડમાં → brahmandma`, `અક્ષરધામમાં → akshardhamma`) and independent `ઋ → ru` in Gujarati-readable mode.
- Added controlled modern `sv/śv/ṣv/dv → sw/shw/.../dw` readable clusters without blanket `v → w` leakage into words such as `manvantar`, `vasant`, or `ravivar`.
- Added opt-in sentence-start capitalization and exact readable-word overrides (for example `shwetdweep → Shwetdweep`) without semantic proper-name guessing.
- Canonical Brahmic → IAST, exact-source recovery, legacy Plain-English/Hunterian defaults, and 1.1 constructor behavior remain unchanged.
- Added natural-mode scalar/list regression coverage for Gujarati, Devanagari/Hindi, Sanskrit-conservative SMART behavior, and the full Gujarati prose vector.

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
