<?php

declare(strict_types=1);

namespace Lipimala;

use InvalidArgumentException;

require_once __DIR__ . '/TransliterationCore.php';

final readonly class ScriptToIastOptions
{
    public function __construct(
        public UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
        public UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
        public bool $preserveUnmapped = true,
        public bool $preserveEncodedVedicMarks = true,
    ) {}
}

final readonly class ScriptConfig
{
    /**
     * @param array<string,string> $independentVowels
     * @param array<string,string> $vowelSigns
     * @param array<string,string> $consonants
     * @param array<string,string> $signs
     * @param array<string,string> $digits
     */
    public function __construct(
        public string $virama,
        public string $nukta,
        public array $independentVowels,
        public array $vowelSigns,
        public array $consonants,
        public array $signs,
        public array $digits,
    ) {}
}

final class BrahmicToIastConverter
{
    private function __construct() {}

    public static function convert(string $inputText, ScriptConfig $config, ScriptToIastOptions $options): string
    {
        $visible = stripExactSourceMetadata($inputText);
        $normalized = normalizeUnicode($visible, $options->inputNormalization);
        $chars = Unicode::split($normalized);
        $out = [];
        $i = 0;

        while ($i < count($chars)) {
            $ch = $chars[$i];

            $independent = $config->independentVowels[$ch] ?? null;
            if ($independent !== null) {
                $out[] = $independent;
                ++$i;
                continue;
            }

            $consonantKey = $ch;
            $consonantWidth = 1;
            if ($i + 1 < count($chars) && $chars[$i + 1] === $config->nukta) {
                $withNukta = $ch . $config->nukta;
                if (array_key_exists($withNukta, $config->consonants)) {
                    $consonantKey = $withNukta;
                    $consonantWidth = 2;
                }
            }

            $consonant = $config->consonants[$consonantKey] ?? null;
            if ($consonant !== null) {
                $out[] = $consonant;
                $i += $consonantWidth;
                if ($i < count($chars)) {
                    $next = $chars[$i];
                    if (array_key_exists($next, $config->vowelSigns)) {
                        $out[] = $config->vowelSigns[$next];
                        ++$i;
                        continue;
                    }

                    if ($next === $config->virama) {
                        ++$i;
                        continue;
                    }
                }

                $out[] = 'a';
                continue;
            }

            if (array_key_exists($ch, $config->vowelSigns)) {
                $out[] = $config->vowelSigns[$ch];
                ++$i;
                continue;
            }

            $sign = $config->signs[$ch] ?? null;
            if ($sign !== null) {
                $out[] = $sign;
                ++$i;
                continue;
            }

            $digit = $config->digits[$ch] ?? null;
            if ($digit !== null) {
                $out[] = $digit;
                ++$i;
                continue;
            }

            if (isEncodedVedicMark($ch)) {
                if ($options->preserveEncodedVedicMarks) {
                    $cp = Unicode::ord($ch);
                    $out[] = match ($cp) {
                        0x0951 => "\u{0301}",
                        0x0952 => "\u{0300}",
                        0x1CDA => "\u{0302}",
                        default => $ch,
                    };
                }

                ++$i;
                continue;
            }

            if ($options->preserveUnmapped) {
                $out[] = $ch;
            }

            ++$i;
        }

        $canonical = self::reattachVedicAccentsToVowels(implode('', $out));
        return normalizeUnicode($canonical, $options->outputNormalization);
    }

    private static function reattachVedicAccentsToVowels(string $text): string
    {
        $chars = Unicode::split($text);
        $i = 0;
        while ($i < count($chars)) {
            $ch = $chars[$i];
            if (!self::isLatinVedicAccent($ch)) {
                ++$i;
                continue;
            }

            $target = self::findAccentVowelTarget($chars, $i);
            if ($target === null) {
                ++$i;
                continue;
            }

            $accent = $chars[$i];
            array_splice($chars, $i, 1);
            array_splice($chars, $target + 1, 0, [$accent]);
            ++$i;
        }

        return implode('', $chars);
    }

    /** @param list<string> $chars */
    private static function findAccentVowelTarget(array $chars, int $accentIndex): ?int
    {
        $target = $accentIndex - 1;
        if ($target < 0) { return null; }

        if ($chars[$target] === "\u{0310}" && $target - 1 >= 0 && $chars[$target - 1] === 'm') {
            $target -= 2;
        } elseif ($chars[$target] === 'ḥ' || $chars[$target] === 'ṃ') {
            --$target;
        }

        while ($target >= 0 && self::isNonAccentCombiningMark($chars[$target])) {
            --$target;
        }

        if ($target < 0) { return null; }

        return self::isLatinVowel($chars[$target]) ? $target : null;
    }

    private static function isLatinVedicAccent(string $ch): bool
    {
        return in_array($ch, ["\u{0301}", "\u{0300}", "\u{0302}"], true);
    }

    private static function isNonAccentCombiningMark(string $ch): bool
    {
        return isUnicodeCombiningMark($ch) && !self::isLatinVedicAccent($ch);
    }

    private static function isLatinVowel(string $ch): bool
    {
        return in_array(Unicode::lower($ch), ['a','ā','i','ī','u','ū','ṛ','ṝ','ḷ','ḹ','e','o','ă','ê','ĕ','ô','ŏ','æ','œ'], true);
    }
}

function devanagariScriptConfig(): ScriptConfig
{
    static $config = null;
    if ($config instanceof ScriptConfig) { return $config; }

    $digits = [];
    for ($i = 0; $i < 10; ++$i) { $digits[Unicode::chr(0x0966 + $i)] = (string) $i; }

    $config = new ScriptConfig(
        virama: '्', nukta: '़',
        independentVowels: [
            'अ' => 'a','आ' => 'ā','इ' => 'i','ई' => 'ī','उ' => 'u','ऊ' => 'ū','ऋ' => 'ṛ','ॠ' => 'ṝ','ऌ' => 'ḷ','ॡ' => 'ḹ',
            'ऄ' => 'ă','ऍ' => 'ê','ऎ' => 'ĕ','ए' => 'e','ऐ' => 'ai','ऑ' => 'ô','ऒ' => 'ŏ','ओ' => 'o','औ' => 'au',
            'ॲ' => 'æ','ॳ' => 'oe','ॴ' => 'ōe','ॵ' => 'aw','ॶ' => 'ue','ॷ' => 'ūe',
        ],
        vowelSigns: [
            'ा' => 'ā','ि' => 'i','ी' => 'ī','ु' => 'u','ू' => 'ū','ृ' => 'ṛ','ॄ' => 'ṝ','ॢ' => 'ḷ','ॣ' => 'ḹ',
            'ॅ' => 'ê','ॆ' => 'ĕ','े' => 'e','ै' => 'ai','ॉ' => 'ô','ॊ' => 'ŏ','ो' => 'o','ौ' => 'au',
            'ऺ' => 'oe','ऻ' => 'ōe','ॏ' => 'aw','ॖ' => 'ue','ॗ' => 'ūe',
        ],
        consonants: [
            'क' => 'k','ख' => 'kh','ग' => 'g','घ' => 'gh','ङ' => 'ṅ','च' => 'c','छ' => 'ch','ज' => 'j','झ' => 'jh','ञ' => 'ñ',
            'ट' => 'ṭ','ठ' => 'ṭh','ड' => 'ḍ','ढ' => 'ḍh','ण' => 'ṇ','त' => 't','थ' => 'th','द' => 'd','ध' => 'dh','न' => 'n',
            'प' => 'p','फ' => 'ph','ब' => 'b','भ' => 'bh','म' => 'm','य' => 'y','र' => 'r','ल' => 'l','व' => 'v','श' => 'ś','ष' => 'ṣ','स' => 's','ह' => 'h',
            'ऴ' => 'ḻ','ळ' => 'ḷ','ऴ' => 'ḻ','क़' => 'q','ख़' => 'x','ग़' => 'ġ','ज़' => 'z','ड़' => 'ṛ','ढ़' => 'ṛh','फ़' => 'f','य़' => 'ẏ',
            'ऩ' => 'ṉ','ऱ' => 'ṟ','त़' => 'ṯ','द़' => 'ḏ','ह़' => 'ẖ','स़' => 's̱','ॸ' => 'ḍḍ','ॹ' => 'ž','ॺ' => 'yy','ॻ' => 'gg','ॼ' => 'jj','ॾ' => 'ddd','ॿ' => 'bb','ॽ' => 'ʔ',
        ],
        signs: ['ँ' => "\u{0310}",'ं' => 'ṃ','ः' => 'ḥ','ऽ' => "'",'ॐ' => 'oṃ','॑' => "\u{0301}",'॒' => "\u{0300}",'᳚' => "\u{0302}",'ᳪ' => "m\u{0310}",'।' => '|','॥' => '||'],
        digits: $digits,
    );
    return $config;
}

function gujaratiScriptConfig(): ScriptConfig
{
    static $config = null;
    if ($config instanceof ScriptConfig) { return $config; }

    $digits = [];
    for ($i = 0; $i < 10; ++$i) { $digits[Unicode::chr(0x0AE6 + $i)] = (string) $i; }

    $config = new ScriptConfig(
        virama: '્', nukta: '઼',
        independentVowels: [
            'અ' => 'a','આ' => 'ā','ઇ' => 'i','ઈ' => 'ī','ઉ' => 'u','ઊ' => 'ū','ઋ' => 'ṛ','ૠ' => 'ṝ','ઌ' => 'ḷ','ૡ' => 'ḹ',
            'ઍ' => 'ĕ','એ' => 'e','ઐ' => 'ai','ઑ' => 'ŏ','ઓ' => 'o','ઔ' => 'au',
        ],
        vowelSigns: ['ા' => 'ā','િ' => 'i','ી' => 'ī','ુ' => 'u','ૂ' => 'ū','ૃ' => 'ṛ','ૄ' => 'ṝ','ૢ' => 'ḷ','ૣ' => 'ḹ','ૅ' => 'ĕ','ે' => 'e','ૈ' => 'ai','ૉ' => 'ŏ','ો' => 'o','ૌ' => 'au'],
        consonants: [
            'ક' => 'k','ખ' => 'kh','ગ' => 'g','ઘ' => 'gh','ઙ' => 'ṅ','ચ' => 'c','છ' => 'ch','જ' => 'j','ઝ' => 'jh','ઞ' => 'ñ',
            'ટ' => 'ṭ','ઠ' => 'ṭh','ડ' => 'ḍ','ઢ' => 'ḍh','ણ' => 'ṇ','ત' => 't','થ' => 'th','દ' => 'd','ધ' => 'dh','ન' => 'n',
            'પ' => 'p','ફ' => 'ph','બ઼' => 'ɓ','બ' => 'b','ભ' => 'bh','મ' => 'm','ય' => 'y','ર' => 'r','લ' => 'l','વ' => 'v','શ' => 'ś','ષ' => 'ṣ','સ' => 's','હ' => 'h',
            'ળ' => 'ḷ','ૹ' => 'ḻ','ક઼' => 'q','ખ઼' => 'x','ગ઼' => 'ġ','જ઼' => 'z','ડ઼' => 'ṛ','ઢ઼' => 'ṛh','ફ઼' => 'f','ય઼' => 'ẏ','ન઼' => 'ṉ','ર઼' => 'ṟ','ત઼' => 'ṯ','દ઼' => 'ḏ','હ઼' => 'ẖ','સ઼' => 's̱',
        ],
        signs: ['ઁ' => "\u{0310}",'ં' => 'ṃ','ઃ' => 'ḥ','ઽ' => "'",'ૐ' => 'oṃ','॑' => "\u{0301}",'॒' => "\u{0300}",'᳚' => "\u{0302}",'ᳪ' => "m\u{0310}",'।' => '|','॥' => '||'],
        digits: $digits,
    );
    return $config;
}

function toIastFromDevanagari(string $text, ?ScriptToIastOptions $options = null): string
{
    $exact = recoverEmbeddedExactSource($text);
    return $exact ?? BrahmicToIastConverter::convert($text, devanagariScriptConfig(), $options ?? new ScriptToIastOptions);
}

function to_iast_from_devanagari(string $text, ?ScriptToIastOptions $options = null): string
{
    return toIastFromDevanagari($text, $options);
}

function toExactIastFromDevanagari(string $text): string
{
    if ($text === '') { return ''; }

    $exact = recoverEmbeddedExactSource($text);
    if ($exact === null) {
        throw new InvalidArgumentException('No valid embedded exact-source metadata was found. Convert with IastToDevanagariOptions(embedExactSourceMetadata: true).');
    }

    return $exact;
}

function to_exact_iast_from_devanagari(string $text): string
{
    return toExactIastFromDevanagari($text);
}

function toCanonicalIastFromDevanagari(string $text, ?ScriptToIastOptions $options = null): string
{
    return BrahmicToIastConverter::convert($text, devanagariScriptConfig(), $options ?? new ScriptToIastOptions);
}

function to_canonical_iast_from_devanagari(string $text, ?ScriptToIastOptions $options = null): string
{
    return toCanonicalIastFromDevanagari($text, $options);
}

function toIastFromGujarati(string $text, ?ScriptToIastOptions $options = null): string
{
    $exact = recoverEmbeddedExactSource($text);
    return $exact ?? BrahmicToIastConverter::convert($text, gujaratiScriptConfig(), $options ?? new ScriptToIastOptions);
}

function to_iast_from_gujarati(string $text, ?ScriptToIastOptions $options = null): string
{
    return toIastFromGujarati($text, $options);
}

function toExactIastFromGujarati(string $text): string
{
    if ($text === '') { return ''; }

    $exact = recoverEmbeddedExactSource($text);
    if ($exact === null) {
        throw new InvalidArgumentException('No valid embedded exact-source metadata was found. Convert with IastToGujaratiOptions(embedExactSourceMetadata: true).');
    }

    return $exact;
}

function to_exact_iast_from_gujarati(string $text): string
{
    return toExactIastFromGujarati($text);
}

function toCanonicalIastFromGujarati(string $text, ?ScriptToIastOptions $options = null): string
{
    return BrahmicToIastConverter::convert($text, gujaratiScriptConfig(), $options ?? new ScriptToIastOptions);
}

function to_canonical_iast_from_gujarati(string $text, ?ScriptToIastOptions $options = null): string
{
    return toCanonicalIastFromGujarati($text, $options);
}

function hasExactDevanagariIastSourceMetadata(string $text): bool
{
    return hasEmbeddedExactSource($text);
}

function has_exact_devanagari_iast_source_metadata(string $text): bool
{
    return hasExactDevanagariIastSourceMetadata($text);
}

function visibleDevanagariWithoutExactSourceMetadata(string $text): string
{
    return stripExactSourceMetadata($text);
}

function hasExactGujaratiIastSourceMetadata(string $text): bool
{
    return hasEmbeddedExactSource($text);
}

function has_exact_gujarati_iast_source_metadata(string $text): bool
{
    return hasExactGujaratiIastSourceMetadata($text);
}

function visibleGujaratiWithoutExactSourceMetadata(string $text): string
{
    return stripExactSourceMetadata($text);
}
