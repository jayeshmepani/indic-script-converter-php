# Changelog

## 1.0.0 — PHP 8.3+ port

- Ported the current Dart/Python transliteration core to strict-typed PHP 8.3+.
- Preserved `extendedIndic` as the default forward profile.
- Ported Latin/IAST → Devanagari and Gujarati conversion, including Vedic marks, NFD/NFC input, extended Indic mappings, punctuation/digit/OM policies, unknown-mark policies, contextual vocalic/flap handling, and exact-source metadata.
- Ported Latin/IAST → plain-English and explicit Hunterian rendering.
- Ported Devanagari/Gujarati → canonical IAST, including reverse Vedic accent placement.
- Ported lossless result envelopes and checksummed invisible Unicode-Tag exact-source metadata.
- Added direct Devanagari ↔ Gujarati conversion with typed exact-source metadata.
- Bundled Unicode 17.0.0 canonical normalization, combining-class, mark-category, and simple case data; no PHP `intl` or `mbstring` runtime dependency is required.
- Added 497-case aligned corpora, 22 Vedic fixtures, seven CLI output generators, and an executable parity/regression suite.
