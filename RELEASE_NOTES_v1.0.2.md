# lipimala PHP v1.0.2 — Bulk Array Transliteration & PHP 8.3 Quality Suite

**`lipimala` PHP v1.0.2** brings native bulk array transliteration helpers for PHP 8.3+, full options customization, and 100% test assertion parity.

---

### What's Changed in v1.0.2

- **Bulk Array Transliteration Functions**:
  - `toDevanagariFromIastList(array $items, ?IastToDevanagariOptions $options = null): array`
  - `toGujaratiFromIastList(array $items, ?IastToGujaratiOptions $options = null): array`
  - `toPlainEnglishFromIastList(array $items, ?IastPlainEnglishOptions $options = null): array`
  - `toCanonicalIastFromDevanagariList(array $items, ?ScriptToIastOptions $options = null): array`
  - `toCanonicalIastFromGujaratiList(array $items, ?ScriptToIastOptions $options = null): array`
  - `toCanonicalGujaratiFromDevanagariList(array $items, ?IndicScriptConversionOptions $options = null): array`
  - `toCanonicalDevanagariFromGujaratiList(array $items, ?IndicScriptConversionOptions $options = null): array`
  - `toDevanagariList(array $items, ?IastToDevanagariOptions $options = null): array`
  - `toGujaratiList(array $items, ?IastToGujaratiOptions $options = null): array`
  - `toPlainEnglishList(array $items, ?IastPlainEnglishOptions $options = null): array`
- **Autoload Integration**: Global list helpers autoloaded via Composer `autoload.files`.
- **PHP Quality Suite**: Passed 100% cleanly with Laravel Pint, Rector, and PHPUnit (29 tests, 7,585 assertions).

---

### PHP Usage Example

```php
use Lipimala\IastToDevanagariDigitPolicy;
use Lipimala\IastToDevanagariOptions;
use function Lipimala\toCanonicalGujaratiFromDevanagariList;
use function Lipimala\toDevanagariFromIastList;

$items = ['Kṛṣṇa', 'Rāma 123', 'jñāna'];

// Bulk convert Latin IAST array to Devanagari
$deva = toDevanagariFromIastList($items);
// -> ['कृष्ण', 'राम 123', 'ज्ञान']

// Bulk convert with custom options (script digits)
$devaScriptDigits = toDevanagariFromIastList(
    $items,
    new IastToDevanagariOptions(digitPolicy: IastToDevanagariDigitPolicy::ConvertToScript)
);
// -> ['कृष्ण', 'राम १२३', 'ज्ञान']

// Bulk direct Devanagari -> Gujarati
$gujr = toCanonicalGujaratiFromDevanagariList($deva);
// -> ['કૃષ્ણ', 'રામ 123', 'જ્ઞાન']
```
