<?php

declare(strict_types=1);

namespace Lipimala;

use InvalidArgumentException;
use RangeException;

require_once __DIR__ . '/TransliterationCore.php';
require_once __DIR__ . '/LatnIastTranscription.php';

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

const GUJARATI_SUKUN = 'ૺ';
const GUJARATI_SHADDA = 'ૻ';
const GUJARATI_MADDAH = 'ૼ';

function gujaratiClusterTransliteration(string $base, string $mark): ?string
{
    if ($mark === '૾') {
        if ($base === 'સ') {
            return 'th';
        }

        if ($base === 'ઝ') {
            return 'dh';
        }
    }

    if ($mark === '૿' && $base === 'ઝ') {
        return 'ẓ';
    }

    if ($mark === '૽') {
        return [
            'ક' => 'q', 'ખ' => 'x', 'ગ' => 'ġ', 'ત' => 'ṭ', 'સ' => 'ṣ', 'ઝ' => 'ḍ',
        ][$base] ?? null;
    }

    return null;
}

function vedicSegmentalLatin(int|string $ch): ?string
{
    $cp = is_int($ch) ? $ch : Unicode::ord($ch);
    if (isVedicVisargaMark($cp)) {
        return 'ḥ';
    }

    if (isVedicNasalMark($cp)) {
        return 'ṃ';
    }

    if (isVedicSegmentalSign($cp)) {
        if ($cp === 0x1CF2 || $cp === 0x1CF3) {
            return 'ḥ';
        }

        if ($cp === 0x1CF5) {
            return 'ḫ';
        }

        return 'f';
    }

    if ($cp >= 0xA8F2 && $cp <= 0xA8F6) {
        return $cp === 0xA8F4 ? 'ṃṃ' : 'ṃ';
    }

    if ($cp === 0xA8F7) {
        return 'ṃ\'';
    }

    return null;
}

function lengthenCanonicalVowel(string $vowel): string
{
    return [
        'a' => 'ā', 'i' => 'ī', 'u' => 'ū', 'e' => 'ē', 'o' => 'ō',
        'ă' => 'ā', 'ĕ' => 'ē', 'ŏ' => 'ō', 'ĭ' => 'ī',
    ][$vowel] ?? $vowel;
}

/**
 * @return array{vowel:string,width:int}|null
 */
function readBrahmicVowelSign(array $chars, int $index, ScriptConfig $config): ?array
{
    if ($index >= count($chars)) { return null; }

    $ch = $chars[$index];
    if ($config === devanagariScriptConfig() && $ch === 'ॎ') {
        $following = $chars[$index + 1] ?? null;
        if ($following === 'े') { return ['vowel' => 'ai', 'width' => 2]; }

        if ($following === 'ा') { return ['vowel' => 'o', 'width' => 2]; }

        if ($following === 'ो') { return ['vowel' => 'au', 'width' => 2]; }

        return ['vowel' => 'e', 'width' => 1];
    }

    if (!array_key_exists($ch, $config->vowelSigns)) { return null; }

    return ['vowel' => $config->vowelSigns[$ch], 'width' => 1];
}

final class BrahmicToIastConverter
{
    private function __construct() {}

    public static function convert(string $inputText, ScriptConfig $config, ScriptToIastOptions $options, string $inherentVowel = 'a', ?array $readableOverrides = null): string
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
                $latin = $readableOverrides['independentVowels'][$ch] ?? $independent;
                ++$i;
                if ($config === gujaratiScriptConfig() && ($ch === 'અ' || $ch === 'ઇ') && ($chars[$i] ?? null) === '૽') {
                    $latin = 'ʿ' . $latin;
                    ++$i;
                }

                if ($config === gujaratiScriptConfig()) {
                    while ($i < count($chars)) {
                        if (($chars[$i] ?? null) === GUJARATI_MADDAH) {
                            $latin = lengthenCanonicalVowel($latin);
                            ++$i;
                        } elseif (($chars[$i] ?? null) === GUJARATI_SUKUN) {
                            // On an independent vowel U+0AFA marks a pause, not vowel deletion.
                            ++$i;
                        } else {
                            break;
                        }
                    }
                }

                $out[] = $latin;
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
                $latin = $readableOverrides['consonants'][$consonantKey] ?? $consonant;
                $cursor = $i + $consonantWidth;
                $geminated = false;
                $sukun = false;
                $maddah = false;

                if ($config === gujaratiScriptConfig()) {
                    $specialNuktaSeen = false;
                    while ($cursor < count($chars)) {
                        $mark = $chars[$cursor];
                        if (!$specialNuktaSeen && isGujaratiConsonantNukta($mark)) {
                            $latin = gujaratiClusterTransliteration($consonantKey, $mark) ?? $latin . $mark;
                            $specialNuktaSeen = true;
                            ++$cursor;
                        } elseif ($mark === GUJARATI_SHADDA) {
                            $geminated = true;
                            ++$cursor;
                        } elseif ($mark === GUJARATI_SUKUN) {
                            $sukun = true;
                            ++$cursor;
                        } elseif ($mark === GUJARATI_MADDAH) {
                            $maddah = true;
                            ++$cursor;
                        } else {
                            break;
                        }
                    }
                }

                $vowelInfo = readBrahmicVowelSign($chars, $cursor, $config);
                $explicitVowel = null;
                if ($vowelInfo !== null) {
                    $explicitVowel = $vowelInfo['vowel'];
                    $cursor += $vowelInfo['width'];
                    // An explicit vowel wins over a preceding/tolerated sukun.
                    $sukun = false;
                    if ($config === gujaratiScriptConfig() && ($chars[$cursor] ?? null) === GUJARATI_MADDAH) {
                        $maddah = true;
                        ++$cursor;
                    }
                }

                $virama = false;
                if (($chars[$cursor] ?? null) === $config->virama) {
                    $virama = true;
                    ++$cursor;
                }

                if ($config === gujaratiScriptConfig()) {
                    while ($cursor < count($chars)) {
                        $mark = $chars[$cursor];
                        if ($mark === GUJARATI_MADDAH) {
                            $maddah = true;
                            ++$cursor;
                        } elseif ($mark === GUJARATI_SUKUN) {
                            // With an explicit vowel this is a tolerated pause mark, not a killer.
                            if ($explicitVowel === null) { $sukun = true; }

                            ++$cursor;
                        } elseif ($mark === GUJARATI_SHADDA) {
                            $geminated = true;
                            ++$cursor;
                        } else {
                            break;
                        }
                    }
                }

                $out[] = $latin;
                if ($geminated) { $out[] = $latin; }

                $i = $cursor;
                if ($virama || $sukun) { continue; }

                if ($geminated && $explicitVowel === null && !$maddah) { continue; }

                $vowel = $explicitVowel ?? $inherentVowel;
                if ($maddah) { $vowel = lengthenCanonicalVowel($vowel === $inherentVowel ? 'a' : $vowel); }

                $out[] = $vowel;
                continue;
            }

            $standaloneVowel = readBrahmicVowelSign($chars, $i, $config);
            if ($standaloneVowel !== null) {
                $vowel = $standaloneVowel['vowel'];
                $i += $standaloneVowel['width'];
                if ($config === gujaratiScriptConfig() && ($chars[$i] ?? null) === GUJARATI_MADDAH) {
                    $vowel = lengthenCanonicalVowel($vowel);
                    ++$i;
                }

                $out[] = $vowel;
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

            $segmental = vedicSegmentalLatin($ch);
            if ($segmental !== null) {
                $out[] = $segmental;
                if ($options->preserveEncodedVedicMarks) { $out[] = $ch; }

                ++$i;
                continue;
            }

            if (isVedicToneCantillationMark($ch) || isSamavedaCantillationMark($ch)) {
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

            if (isBrahmicFormatControl($ch)) {
                ++$i;
                continue;
            }

            if ($ch === "\u{25CC}") {
                if ($options->preserveUnmapped) { $out[] = $ch; }

                ++$i;
                continue;
            }

            if ($options->preserveUnmapped) { $out[] = $ch; }

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
        return in_array(Unicode::lower($ch), ['a', 'ā', 'i', 'ī', 'u', 'ū', 'ṛ', 'ṝ', 'ḷ', 'ḹ', 'e', 'ē', 'o', 'ō', 'ă', 'ê', 'ĕ', 'ĭ', 'ô', 'ŏ', 'æ', 'œ'], true);
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
            'अ' => 'a', 'आ' => 'ā', 'इ' => 'i', 'ई' => 'ī', 'उ' => 'u', 'ऊ' => 'ū', 'ऋ' => 'ṛ', 'ॠ' => 'ṝ', 'ऌ' => 'ḷ', 'ॡ' => 'ḹ',
            'ऄ' => 'ă', 'ऍ' => 'ê', 'ऎ' => 'ĕ', 'ए' => 'e', 'ऐ' => 'ai', 'ऑ' => 'ô', 'ऒ' => 'ŏ', 'ओ' => 'o', 'औ' => 'au',
            'ॲ' => 'æ', 'ॳ' => 'oe', 'ॴ' => 'ōe', 'ॵ' => 'aw', 'ॶ' => 'ue', 'ॷ' => 'ūe',
            "\u{0955}" => 'ē', "\u{A8FE}" => 'aĭ',
        ],
        vowelSigns: [
            'ा' => 'ā', 'ि' => 'i', 'ी' => 'ī', 'ु' => 'u', 'ू' => 'ū', 'ृ' => 'ṛ', 'ॄ' => 'ṝ', 'ॢ' => 'ḷ', 'ॣ' => 'ḹ',
            'ॅ' => 'ê', 'ॆ' => 'ĕ', 'े' => 'e', 'ै' => 'ai', 'ॉ' => 'ô', 'ॊ' => 'ŏ', 'ो' => 'o', 'ौ' => 'au',
            'ऺ' => 'oe', 'ऻ' => 'ōe', 'ॏ' => 'aw', 'ॖ' => 'ue', 'ॗ' => 'ūe',
            "\u{094E}" => 'e', "\u{0955}" => 'ē', "\u{A8FF}" => 'aĭ',
        ],
        consonants: [
            'क' => 'k', 'ख' => 'kh', 'ग' => 'g', 'घ' => 'gh', 'ङ' => 'ṅ', 'च' => 'c', 'च़' => 'ċ', 'छ' => 'ch', 'ज' => 'j', 'झ' => 'jh', 'ञ' => 'ñ',
            'ट' => 'ṭ', 'ठ' => 'ṭh', 'ड' => 'ḍ', 'ढ' => 'ḍh', 'ण' => 'ṇ', 'त' => 't', 'थ' => 'th', 'द' => 'd', 'ध' => 'dh', 'न' => 'n',
            'प' => 'p', 'फ' => 'ph', 'ब' => 'b', 'भ' => 'bh', 'म' => 'm', 'य' => 'y', 'र' => 'r', 'ल' => 'l', 'व' => 'v', 'श' => 'ś', 'ष' => 'ṣ', 'स' => 's', 'ह' => 'h',
            'ऴ' => 'ḻ', 'ळ' => 'ḷ', 'ऴ' => 'ḻ', 'क़' => 'q', 'ख़' => 'x', 'ग़' => 'ġ', 'ज़' => 'z', 'ड़' => 'ṛ', 'ढ़' => 'ṛh', 'फ़' => 'f', 'य़' => 'ẏ',
            'ऩ' => 'ṉ', 'ऱ' => 'ṟ', 'त़' => 'ṯ', 'द़' => 'ḏ', 'ह़' => 'ẖ', 'स़' => 's̱', 'ॸ' => 'ḍḍ', 'ॹ' => 'ž', 'ॺ' => 'yy', 'ॻ' => 'ɠ', 'ॼ' => 'ʄ', 'ॾ' => 'ɗ', 'ॿ' => 'ɓ', 'ॽ' => 'ʔ',
            "\u{0929}" => 'ṉ', "\u{0931}" => 'ṟ', "\u{0958}" => 'q', "\u{0959}" => 'x', "\u{095A}" => 'ġ',
            "\u{095B}" => 'z', "\u{095C}" => 'ṛ', "\u{095D}" => 'ṛh', "\u{095E}" => 'f', "\u{095F}" => 'ẏ',
            "\u{11B0A}" => 'ɗ',
        ],
        signs: ['ँ' => "\u{0310}", 'ं' => 'ṃ', 'ः' => 'ḥ', 'ऽ' => "'", 'ॐ' => 'oṃ', '॑' => "\u{0301}", '॒' => "\u{0300}", '᳚' => "\u{0302}", 'ᳪ' => "m\u{0310}", '।' => '|', '॥' => '||',
            "\u{0900}" => "\u{0310}", "\u{0953}" => "\u{0300}", "\u{0954}" => "\u{0301}", "\u{0970}" => "\u{0970}", "\u{0971}" => "\u{0971}",
            "\u{A8FC}" => "\u{A8FC}", "\u{A8FD}" => 'oṃ',
            "\u{A8F8}" => "\u{A8F8}", "\u{A8F9}" => "\u{A8F9}", "\u{A8FA}" => "\u{A8FA}", "\u{A8FB}" => "\u{A8FB}",
            "\u{11B00}" => "\u{11B00}", "\u{11B01}" => "\u{11B01}", "\u{11B02}" => "\u{11B02}", "\u{11B03}" => "\u{11B03}", "\u{11B04}" => "\u{11B04}",
            "\u{11B05}" => "\u{11B05}", "\u{11B06}" => "\u{11B06}", "\u{11B07}" => "\u{11B07}", "\u{11B08}" => "\u{11B08}", "\u{11B09}" => "\u{11B09}",
            "\u{A830}" => "\u{A830}", "\u{A831}" => "\u{A831}", "\u{A832}" => "\u{A832}", "\u{A833}" => "\u{A833}", "\u{A834}" => "\u{A834}",
            "\u{A835}" => "\u{A835}", "\u{A836}" => "\u{A836}", "\u{A837}" => "\u{A837}", "\u{A838}" => "\u{A838}", "\u{A839}" => "\u{A839}"],
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
            'અ' => 'a', 'આ' => 'ā', 'ઇ' => 'i', 'ઈ' => 'ī', 'ઉ' => 'u', 'ઊ' => 'ū', 'ઋ' => 'ṛ', 'ૠ' => 'ṝ', 'ઌ' => 'ḷ', 'ૡ' => 'ḹ',
            'ઍ' => 'ĕ', 'એ' => 'e', 'ઐ' => 'ai', 'ઑ' => 'ŏ', 'ઓ' => 'o', 'ઔ' => 'au',
        ],
        vowelSigns: ['ા' => 'ā', 'િ' => 'i', 'ી' => 'ī', 'ુ' => 'u', 'ૂ' => 'ū', 'ૃ' => 'ṛ', 'ૄ' => 'ṝ', 'ૢ' => 'ḷ', 'ૣ' => 'ḹ', 'ૅ' => 'ĕ', 'ે' => 'e', 'ૈ' => 'ai', 'ૉ' => 'ŏ', 'ો' => 'o', 'ૌ' => 'au'],
        consonants: [
            'ક' => 'k', 'ખ' => 'kh', 'ગ' => 'g', 'ઘ' => 'gh', 'ઙ' => 'ṅ', 'ચ' => 'c', 'છ' => 'ch', 'જ' => 'j', 'ઝ' => 'jh', 'ઞ' => 'ñ',
            'ટ' => 'ṭ', 'ઠ' => 'ṭh', 'ડ' => 'ḍ', 'ઢ' => 'ḍh', 'ણ' => 'ṇ', 'ત' => 't', 'થ' => 'th', 'દ' => 'd', 'ધ' => 'dh', 'ન' => 'n',
            'પ' => 'p', 'ફ' => 'ph', 'બ઼' => 'ɓ', 'બ' => 'b', 'ભ' => 'bh', 'મ' => 'm', 'ય' => 'y', 'ર' => 'r', 'લ' => 'l', 'વ' => 'v', 'શ' => 'ś', 'ષ' => 'ṣ', 'સ' => 's', 'હ' => 'h',
            'ળ' => 'ḷ', 'ૹ' => 'ḻ', 'ક઼' => 'q', 'ખ઼' => 'x', 'ગ઼' => 'ġ', 'જ઼' => 'z', 'ડ઼' => 'ṛ', 'ઢ઼' => 'ṛh', 'ફ઼' => 'f', 'ય઼' => 'ẏ', 'ન઼' => 'ṉ', 'ર઼' => 'ṟ', 'ત઼' => 'ṯ', 'દ઼' => 'ḏ', 'હ઼' => 'ẖ', 'સ઼' => 's̱',
        ],
        signs: ['ઁ' => "\u{0310}", 'ં' => 'ṃ', 'ઃ' => 'ḥ', 'ઽ' => "'", 'ૐ' => 'oṃ', '॑' => "\u{0301}", '॒' => "\u{0300}", '᳚' => "\u{0302}", 'ᳪ' => "m\u{0310}", '।' => '|', '॥' => '||',
            "\u{0AF0}" => "\u{0AF0}", "\u{0AF1}" => "\u{0AF1}",
            "\u{A830}" => "\u{A830}", "\u{A831}" => "\u{A831}", "\u{A832}" => "\u{A832}", "\u{A833}" => "\u{A833}", "\u{A834}" => "\u{A834}",
            "\u{A835}" => "\u{A835}", "\u{A836}" => "\u{A836}", "\u{A837}" => "\u{A837}", "\u{A838}" => "\u{A838}", "\u{A839}" => "\u{A839}"],
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

const INHERENT_SCHWA = "\u{E100}";
// Medial schwa deleted by natural mode. Stays inside the word until vowel length.
const SYLLABLE_BREAK = "\u{E101}";

enum BrahmicLanguage: string
{
    case AUTO = 'auto';
    case HINDI = 'hindi';
    case GUJARATI = 'gujarati';
    case SANSKRIT = 'sanskrit';
}

enum BrahmicSchwaDeletionPolicy: string
{
    case NONE = 'none';
    case FINAL = 'final';
    case LANGUAGE = 'language';
}

enum BrahmicVowelLengthPolicy: string
{
    case COLLAPSE = 'collapse';
    case DOUBLE = 'double';
    case SMART = 'smart';
}

enum BrahmicReadableStyle: string
{
    case LEGACY = 'legacy';
    case NATURAL = 'natural';
}

enum TerminalAnusvaraPolicy: string
{
    case N = 'n';
    case M = 'm';
    case DROP = 'drop';
}

enum AynPolicy: string
{
    case APOSTROPHE = 'apostrophe';
    case DROP = 'drop';
    case PRESERVE = 'preserve';
}

final readonly class BrahmicPlainEnglishOptions
{
    public function __construct(
        public BrahmicLanguage $language = BrahmicLanguage::AUTO,
        public BrahmicSchwaDeletionPolicy $schwaDeletion = BrahmicSchwaDeletionPolicy::LANGUAGE,
        public BrahmicVowelLengthPolicy $vowelLength = BrahmicVowelLengthPolicy::DOUBLE,
        public TerminalAnusvaraPolicy $terminalAnusvara = TerminalAnusvaraPolicy::N,
        public AynPolicy $ayn = AynPolicy::APOSTROPHE,
        public JnaPolicy $jna = JnaPolicy::GYA,
        public NyaPolicy $nya = NyaPolicy::NA,
        public GlottalStopPolicy $glottalStop = GlottalStopPolicy::REMOVE,
        public bool $convertCToCh = true,
        public bool $assimilateAnusvara = true,
        public bool $removeAvagraha = true,
        public bool $collapseWhitespace = false,
        public bool $preserveVedicAccentMarks = false,
        public ?bool $useWForVAfterConsonants = null,
        public BrahmicReadableStyle $readableStyle = BrahmicReadableStyle::LEGACY,
        public bool $capitalizeSentenceStarts = false,
        /** @var array<string,string> */
        public array $readableWordOverrides = [],
    ) {}
}

function isSourceLatinBase(string $ch): bool
{
    $cp = Unicode::ord($ch);

    return ($cp >= 0x41 && $cp <= 0x5A)
        || ($cp >= 0x61 && $cp <= 0x7A)
        || ($cp >= 0x00C0 && $cp <= 0x024F)
        || ($cp >= 0x0250 && $cp <= 0x02FF)
        || ($cp >= 0x1E00 && $cp <= 0x1EFF);
}

function isSourceLatinCombiningMark(string $ch): bool
{
    $cp = Unicode::ord($ch);

    return ($cp >= 0x0300 && $cp <= 0x036F)
        || ($cp >= 0x1AB0 && $cp <= 0x1AFF)
        || ($cp >= 0x1DC0 && $cp <= 0x1DFF)
        || ($cp >= 0xFE20 && $cp <= 0xFE2F);
}

function isSourceLatinPunctuation(string $ch): bool
{
    return $ch === '‘' || $ch === '’';
}

/**
 * @return array{text:string,spans:list<array{0:string,1:string}>}
 */
function protectSourceLatin(string $text): array
{
    $chars = Unicode::split($text);
    $spans = [];
    $out = [];
    $i = 0;
    $nextToken = 0xF0000;
    $count = count($chars);

    $allocateToken = static function () use ($text, &$nextToken): string {
        while ($nextToken <= 0x10FFFD) {
            if ($nextToken > 0xFFFFD && $nextToken < 0x100000) {
                $nextToken = 0x100000;
            }

            $token = Unicode::chr($nextToken);
            ++$nextToken;
            if (!str_contains($text, $token)) {
                return $token;
            }
        }

        throw new RangeException('Too many protected Latin spans in Brahmic transcription input.');
    };

    while ($i < $count) {
        if (isSourceLatinPunctuation($chars[$i])) {
            $token = $allocateToken();
            $spans[] = [$token, $chars[$i]];
            $out[] = $token;
            ++$i;
            continue;
        }

        if (!isSourceLatinBase($chars[$i])) {
            $out[] = $chars[$i];
            ++$i;
            continue;
        }

        $start = $i;
        ++$i;
        while ($i < $count && (isSourceLatinBase($chars[$i]) || isSourceLatinCombiningMark($chars[$i]))) {
            ++$i;
        }

        $token = $allocateToken();
        $spans[] = [$token, implode('', array_slice($chars, $start, $i - $start))];
        $out[] = $token;
    }

    return ['text' => implode('', $out), 'spans' => $spans];
}

/**
 * @param list<array{0:string,1:string}> $spans
 */
function restoreSourceLatin(string $text, array $spans): string
{
    foreach ($spans as [$token, $original]) {
        $text = str_replace($token, $original, $text);
    }

    return $text;
}

/**
 * @return list<array{type:string,text:string,implicit:bool,deleted:bool}>
 */
function tokenizeAnnotatedBrahmicWord(string $word, bool $separateVocalicRh = false): array
{
    $chars = Unicode::split($word);
    $tokens = [];
    $vowels = array_fill_keys(['a', 'ā', 'i', 'ī', 'u', 'ū', 'e', 'ē', 'o', 'ō', 'ṛ', 'ṝ', 'ḷ', 'ḹ', 'ă', 'ê', 'ĕ', 'ĭ', 'ô', 'ŏ', 'æ', 'œ'], true);
    $signs = array_fill_keys(['ṃ', 'ṁ', 'ḥ'], true);
    $aspirated = array_fill_keys(['kh', 'gh', 'ch', 'jh', 'ṭh', 'ḍh', 'th', 'dh', 'ph', 'bh', 'ṛh'], true);
    $counter = count($chars);
    for ($i = 0; $i < $counter;) {
        $ch = $chars[$i];
        if ($ch === INHERENT_SCHWA) {
            $tokens[] = ['type' => 'vowel', 'text' => 'a', 'implicit' => true, 'deleted' => false];
            ++$i;
            continue;
        }

        if ($ch === SYLLABLE_BREAK) {
            $tokens[] = ['type' => 'boundary', 'text' => '', 'implicit' => false, 'deleted' => false];
            ++$i;
            continue;
        }

        $pair = $i + 1 < count($chars) ? $ch . $chars[$i + 1] : '';
        if ($pair === 'ai' || $pair === 'au') {
            $tokens[] = ['type' => 'vowel', 'text' => $pair, 'implicit' => false, 'deleted' => false];
            $i += 2;
            continue;
        }

        $previous = $tokens === [] ? null : $tokens[array_key_last($tokens)];
        if (
            $separateVocalicRh
            && Unicode::lower($pair) === 'ṛh'
            && is_array($previous)
            && $previous['type'] === 'consonant'
        ) {
            $tokens[] = ['type' => 'vowel', 'text' => $ch, 'implicit' => false, 'deleted' => false];
            ++$i;
            continue;
        }

        if (isset($aspirated[Unicode::lower($pair)])) {
            $tokens[] = ['type' => 'consonant', 'text' => $pair, 'implicit' => false, 'deleted' => false];
            $i += 2;
            continue;
        }

        if (isset($vowels[Unicode::lower($ch)])) {
            $tokens[] = ['type' => 'vowel', 'text' => $ch, 'implicit' => false, 'deleted' => false];
            ++$i;
            continue;
        }

        if (isset($signs[$ch])) {
            $tokens[] = ['type' => 'sign', 'text' => $ch, 'implicit' => false, 'deleted' => false];
            ++$i;
            continue;
        }

        if (isUnicodeCombiningMark($ch)) {
            if ($tokens !== []) {
                $tokens[array_key_last($tokens)]['text'] .= $ch;
            } else {
                $tokens[] = ['type' => 'other', 'text' => $ch, 'implicit' => false, 'deleted' => false];
            }

            ++$i;
            continue;
        }

        $type = preg_match('/[A-Za-z\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}]/u', $ch) === 1 ? 'consonant' : 'other';
        $tokens[] = ['type' => $type, 'text' => $ch, 'implicit' => false, 'deleted' => false];
        ++$i;
    }

    return $tokens;
}

/**
 * @param array{type:string,text:string,implicit:bool,deleted:bool} $token
 */
function brahmicBaseConsonant(array $token): string
{
    $value = Unicode::lower($token['text']);
    if (str_ends_with($value, 'h') && Unicode::length($value) > 1) {
        $chars = Unicode::split($value);
        array_pop($chars);
        $value = implode('', $chars);
    }

    return $value;
}

/**
 * @param array{type:string,text:string,implicit:bool,deleted:bool} $token
 */
function isAspiratedBrahmicGha(array $token): bool
{
    return Unicode::lower($token['text']) === 'gh';
}

/**
 * @param array{type:string,text:string,implicit:bool,deleted:bool} $token
 */
function isAspiratedBrahmicDental(array $token): bool
{
    $text = Unicode::lower($token['text']);

    return $text === 'th' || $text === 'ṭh';
}

/**
 * @param array{type:string,text:string,implicit:bool,deleted:bool} $left
 * @param array{type:string,text:string,implicit:bool,deleted:bool} $right
 */
function allowsBrahmicSchwaCluster(array $left, array $right, BrahmicLanguage $language, BrahmicReadableStyle $readableStyle = BrahmicReadableStyle::LEGACY): bool
{
    $a = brahmicBaseConsonant($left);
    $b = brahmicBaseConsonant($right);
    if ($a === '' || $b === '') { return false; }

    if ($language === BrahmicLanguage::GUJARATI) {
        if ($b === 'v' || $b === 'n') { return true; }

        $pair = $a . '+' . $b;
        if (in_array($pair, ['b+t', 'r+m', 'ś+m', 'ṣ+m', 's+m', 'ṇ+l', 'n+l'], true)) {
            return true;
        }

        if ($readableStyle !== BrahmicReadableStyle::NATURAL) { return false; }

        if (($a === 'n' || $a === 'ṇ') && isAspiratedBrahmicGha($right)) { return true; }

        if ($a === 'p' && isAspiratedBrahmicDental($right)) { return true; }

        return in_array($pair, [
            't+l', 'ṭ+l', 'ḍ+m', 'd+m', 'm+m', 'm+d', 't+d', 't+y', 'k+l', 'k+r', 'j+k',
            'r+d', 'l+m', 'ḷ+m', 'ḻ+m', 'l+j', 'ḷ+j', 'ḻ+j',
        ], true);
    }

    $pair = $a . '+' . $b;
    if ($readableStyle === BrahmicReadableStyle::NATURAL && ($a === 'n' || $a === 'ṇ') && isAspiratedBrahmicGha($right)) {
        return true;
    }

    if ($readableStyle === BrahmicReadableStyle::NATURAL && $a === 'p' && isAspiratedBrahmicDental($right)) {
        return true;
    }

    if (
        $readableStyle === BrahmicReadableStyle::NATURAL
        && in_array($pair, [
            't+d', 't+y', 't+n', 'k+l', 'r+d', 'p+n',
            'd+n', 'd+m', 'ḍ+n', 'ḍ+k', 'h+l', 'h+n', 'c+n',
        ], true)
    ) {
        return true;
    }

    if (in_array($b, ['r', 'y', 'v'], true)) { return true; }

    if ($a === 'r' && in_array($b, ['k', 'kh', 'g', 'gh', 'c', 'ch', 'j', 'jh', 't', 'th', 'd', 'dh', 'n', 'p', 'ph', 'b', 'bh', 'm', 's', 'sh', 'h'], true)) {
        return true;
    }

    if (in_array($a, ['m', 'n'], true) && in_array($b, ['k', 'g', 'c', 'j', 't', 'd', 'p', 'b'], true)) { return true; }

    return in_array($a . '+' . $b, [
        'k+t', 'k+s', 'g+d', 'g+n', 't+r', 't+y', 't+v', 'd+r', 'd+y', 'd+v', 'p+r', 'p+y', 'p+v',
        'b+r', 'b+y', 'b+v', 'b+t', 'h+m', 's+t', 's+k', 's+p', 's+m', 's+n', 's+l', 's+v', 'j+v', 'c+v', 'l+y', 'n+y', 'm+y',
    ], true);
}

/**
 * @param list<array{type:string,text:string,implicit:bool,deleted:bool}> $tokens
 */
function shouldKeepBrahmicFinalSchwa(array $tokens, int $lastIndex): bool
{
    if ($lastIndex < 2) { return false; }

    $bearing = $tokens[$lastIndex - 1];
    $previous = $tokens[$lastIndex - 2];
    if ($bearing['type'] !== 'consonant' || $previous['type'] !== 'consonant') { return false; }

    $pair = brahmicBaseConsonant($previous) . '+' . brahmicBaseConsonant($bearing);

    return in_array($pair, ['r+y', 'l+y', 'n+y', 'm+y', 'g+y', 'ś+ṇ', 'ṣ+ṇ', 's+ṇ'], true);
}

/**
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $bearing
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $next
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $following
 */
function isImplicitBrahmicNagarSchwa(?array $bearing, ?array $next, ?array $following): bool
{
    if ($bearing === null || $next === null || $following === null) { return false; }

    if ($bearing['type'] !== 'consonant' || $next['type'] !== 'consonant') { return false; }

    if (($following['implicit'] ?? false) !== true || isAspiratedBrahmicGha($next)) { return false; }

    if (brahmicBaseConsonant($next) !== 'g') { return false; }

    $left = brahmicBaseConsonant($bearing);

    return in_array($left, ['m', 'n', 'ṇ'], true);
}

/**
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $bearing
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $next
 * @param array{type:string,text:string,implicit?:bool,deleted?:bool}|null $following
 */
function isHindiBrahmicNaSuffix(?array $bearing, ?array $next, ?array $following): bool
{
    if ($bearing === null || $next === null || $following === null) { return false; }

    if ($bearing['type'] !== 'consonant' || $next['type'] !== 'consonant') { return false; }

    if ($following['type'] !== 'vowel' || ($following['implicit'] ?? false)) { return false; }

    if (Unicode::lower($following['text']) !== 'ā') { return false; }

    $right = brahmicBaseConsonant($next);
    if ($right !== 'n' && $right !== 'ṇ') { return false; }

    $left = brahmicBaseConsonant($bearing);
    $nasal = ['m', 'n', 'ṇ', 'ñ', 'ṅ'];

    return !in_array($left, $nasal, true) || !in_array($right, $nasal, true);
}

/**
 * @param array{type:string,text:string}|null $next
 * @param array{type:string,text:string}|null $following
 */
function isGujaratiBrahmicThiSuffix(?array $next, ?array $following): bool
{
    if ($next === null || $following === null) { return false; }

    if ($next['type'] !== 'consonant' || $following['type'] !== 'vowel') { return false; }

    $consonant = Unicode::lower($next['text']);
    $vowel = Unicode::lower($following['text']);

    return ($consonant === 'th' || $consonant === 'ṭh') && ($vowel === 'ī' || $vowel === 'i');
}

/**
 * @param array{type:string,text:string}|null $next
 * @param array{type:string,text:string}|null $following
 * @param list<array{type:string,text:string,implicit:bool,deleted:bool}> $tokens
 */
function isGujaratiBrahmicMamSuffix(?array $next, ?array $following, array $tokens, int $vowelIndex): bool
{
    if ($next === null || $following === null) { return false; }

    if ($next['type'] !== 'consonant' || $following['type'] !== 'vowel') { return false; }

    if (brahmicBaseConsonant($next) !== 'm') { return false; }

    $vowel = Unicode::lower($following['text']);
    if ($vowel !== 'ā' && $vowel !== 'a') { return false; }

    $nasalIndex = $vowelIndex + 1;
    while ($nasalIndex < count($tokens) && $tokens[$nasalIndex]['deleted']) { ++$nasalIndex; }

    return isset($tokens[$nasalIndex])
        && $tokens[$nasalIndex]['type'] === 'sign'
        && ($tokens[$nasalIndex]['text'] === 'ṃ' || $tokens[$nasalIndex]['text'] === 'ṁ');
}

/**
 * @param list<array{type:string,text:string,implicit:bool,deleted:bool}> $tokens
 */
function hasBrahmicSchwaLeftVowel(array $tokens, int $beforeIndex, bool $allowCodaSkip): bool
{
    if ($beforeIndex < 0 || !isset($tokens[$beforeIndex])) { return false; }

    if ($tokens[$beforeIndex]['type'] === 'vowel') { return true; }

    if (!$allowCodaSkip || $tokens[$beforeIndex]['type'] !== 'consonant') { return false; }

    if (!in_array(brahmicBaseConsonant($tokens[$beforeIndex]), ['r', 'y', 'l', 'v'], true)) { return false; }

    return $beforeIndex - 1 >= 0 && $tokens[$beforeIndex - 1]['type'] === 'vowel';
}

function naturalizeAnnotatedBrahmicWord(string $word, BrahmicLanguage $language, BrahmicSchwaDeletionPolicy $policy, BrahmicReadableStyle $readableStyle = BrahmicReadableStyle::LEGACY): string
{
    $tokens = tokenizeAnnotatedBrahmicWord($word, $readableStyle === BrahmicReadableStyle::NATURAL);
    if ($policy === BrahmicSchwaDeletionPolicy::NONE || $language === BrahmicLanguage::SANSKRIT) {
        return implode('', array_map(static fn(array $t): string => $t['text'], $tokens));
    }

    $vowelCount = static fn(): int => count(array_filter($tokens, static fn(array $t): bool => $t['type'] === 'vowel' && !$t['deleted']));
    $last = count($tokens) - 1;
    while ($last >= 0 && $tokens[$last]['type'] === 'other') { --$last; }

    if ($last >= 0 && $tokens[$last]['implicit'] && $vowelCount() > 1 && !shouldKeepBrahmicFinalSchwa($tokens, $last)) {
        $tokens[$last]['deleted'] = true;
    }

    if ($policy === BrahmicSchwaDeletionPolicy::LANGUAGE) {
        for ($i = count($tokens) - 2; $i >= 2; --$i) {
            if (!$tokens[$i]['implicit'] || $tokens[$i]['deleted']) { continue; }

            $beforeIndex = $i - 2;
            if ($readableStyle === BrahmicReadableStyle::NATURAL && $language !== BrahmicLanguage::SANSKRIT) {
                while ($beforeIndex >= 0 && $tokens[$beforeIndex]['type'] === 'sign') { --$beforeIndex; }
            }

            $nextIndex = $i + 1;
            while ($nextIndex < count($tokens) && $tokens[$nextIndex]['deleted']) { ++$nextIndex; }

            $afterNext = $nextIndex + 1;
            while ($afterNext < count($tokens) && $tokens[$afterNext]['deleted']) { ++$afterNext; }

            $next = $nextIndex < count($tokens) ? $tokens[$nextIndex] : null;
            $following = $afterNext < count($tokens) ? $tokens[$afterNext] : null;
            $naturalModern = $readableStyle === BrahmicReadableStyle::NATURAL && $language !== BrahmicLanguage::SANSKRIT;
            $mamSuffix = $naturalModern
                && $language === BrahmicLanguage::GUJARATI
                && isGujaratiBrahmicMamSuffix($next, $following, $tokens, $afterNext);
            $thiSuffix = $naturalModern
                && $language === BrahmicLanguage::GUJARATI
                && isGujaratiBrahmicThiSuffix($next, $following);
            $bearing = $tokens[$i - 1];
            $naSuffix = $naturalModern
                && $language === BrahmicLanguage::HINDI
                && isHindiBrahmicNaSuffix($bearing, $next, $following);
            $keepNagarSchwa = $naturalModern
                && $language === BrahmicLanguage::HINDI
                && isImplicitBrahmicNagarSchwa($bearing, $next, $following);

            $rightOnsetHasVowel = $afterNext < count($tokens) && $tokens[$afterNext]['type'] === 'vowel';
            if (
                !$rightOnsetHasVowel
                && $readableStyle === BrahmicReadableStyle::NATURAL
                && $language !== BrahmicLanguage::SANSKRIT
                && $afterNext < count($tokens)
                && $tokens[$afterNext]['type'] === 'consonant'
                && in_array(brahmicBaseConsonant($tokens[$afterNext]), ['r', 'y', 'v', 'l'], true)
            ) {
                $afterCluster = $afterNext + 1;
                while ($afterCluster < count($tokens) && $tokens[$afterCluster]['deleted']) { ++$afterCluster; }

                $rightOnsetHasVowel = $afterCluster < count($tokens) && $tokens[$afterCluster]['type'] === 'vowel';
            }

            if (
                $bearing['type'] === 'consonant'
                && hasBrahmicSchwaLeftVowel($tokens, $beforeIndex, $mamSuffix || $thiSuffix)
                && $nextIndex < count($tokens)
                && $tokens[$nextIndex]['type'] === 'consonant'
                && $rightOnsetHasVowel
                && ($thiSuffix || $naSuffix || (!$keepNagarSchwa && allowsBrahmicSchwaCluster($bearing, $tokens[$nextIndex], $language, $readableStyle)))
            ) {
                $tokens[$i]['deleted'] = true;
                if ($naturalModern) {
                    $tokens[$i]['boundary'] = true;
                }
            }
        }
    }

    return implode('', array_map(
        static fn(array $t): string => $t['deleted']
            ? (empty($t['boundary']) ? '' : SYLLABLE_BREAK)
            : $t['text'],
        $tokens,
    ));
}

function isBrahmicTranscriptionWordChar(string $ch): bool
{
    if ($ch === INHERENT_SCHWA || $ch === SYLLABLE_BREAK) { return true; }

    return preg_match('/[A-Za-z\x{00C0}-\x{024F}\x{0250}-\x{02FF}\x{1E00}-\x{1EFF}\p{M}]/u', $ch) === 1;
}

function naturalizeAnnotatedBrahmicIast(string $text, BrahmicLanguage $language, BrahmicSchwaDeletionPolicy $policy, BrahmicReadableStyle $readableStyle = BrahmicReadableStyle::LEGACY): string
{
    $chars = Unicode::split($text);
    $out = [];
    $counter = count($chars);
    for ($i = 0; $i < $counter;) {
        if (!isBrahmicTranscriptionWordChar($chars[$i])) {
            $out[] = $chars[$i++];
            continue;
        }

        $start = $i++;
        while ($i < count($chars) && isBrahmicTranscriptionWordChar($chars[$i])) { ++$i; }

        $out[] = naturalizeAnnotatedBrahmicWord(
            implode('', array_slice($chars, $start, $i - $start)),
            $language,
            $policy,
            $readableStyle,
        );
    }

    return implode('', $out);
}

/**
 * @param callable(string): string $mapper
 */
function mapBrahmicTranscriptionWords(string $text, callable $mapper): string
{
    $chars = Unicode::split($text);
    $out = [];
    $counter = count($chars);
    for ($i = 0; $i < $counter;) {
        if (!isBrahmicTranscriptionWordChar($chars[$i])) {
            $out[] = $chars[$i++];
            continue;
        }

        $start = $i++;
        while ($i < $counter && isBrahmicTranscriptionWordChar($chars[$i])) { ++$i; }

        $out[] = $mapper(implode('', array_slice($chars, $start, $i - $start)));
    }

    return implode('', $out);
}

function applyNaturalBrahmicClusterSpellings(string $word, BrahmicLanguage $language): string
{
    if ($language === BrahmicLanguage::SANSKRIT) { return $word; }

    return str_replace(
        ['śv', 'Śv', 'ṣv', 'Ṣv', 'sv', 'Sv', 'dv', 'Dv'],
        ['św', 'Św', 'ṣw', 'Ṣw', 'sw', 'Sw', 'dw', 'Dw'],
        $word,
    );
}

function endsWithClosedBrahmicResonant(string $word): bool
{
    $chars = Unicode::split($word);
    $count = count($chars);
    if ($count < 2) { return false; }

    $last = Unicode::lower($chars[$count - 1]);
    if (!str_contains('ryvw', $last)) { return false; }

    $prev = Unicode::lower($chars[$count - 2]);

    return !str_contains('aāiīuūeēoōṛṝḷḹăêĕĭôŏæœ', $prev);
}

function applyBrahmicNaturalMorphology(string $text, BrahmicLanguage $language): string
{
    return mapBrahmicTranscriptionWords($text, static function (string $word) use ($language): string {
        if ($language === BrahmicLanguage::GUJARATI) {
            $word = (string) preg_replace('/māṃthī$/u', 'mathī', $word);
            $word = (string) preg_replace('/māṃ$/u', 'ma', $word);
            if (preg_match('/[aāiīuūeēoōṛṝḷḹăêĕĭôŏæœ].*āṃ$/u', $word) === 1) {
                $word = (string) preg_replace('/āṃ$/u', 'ā', $word);
            }

            $word = (string) preg_replace('/uṃ$/u', 'u', $word);
            $word = (string) preg_replace('/a[ṃṁ][īi]/u', 'ai', $word);
        }

        $word = applyNaturalBrahmicClusterSpellings($word, $language);

        if ($language === BrahmicLanguage::HINDI && str_ends_with($word, 'ie')) {
            $word = substr($word, 0, -2) . 'iye';
        } elseif ($language === BrahmicLanguage::HINDI && str_ends_with($word, 'IE')) {
            $word = substr($word, 0, -2) . 'IYE';
        }

        if ($language !== BrahmicLanguage::SANSKRIT && endsWithClosedBrahmicResonant($word)) {
            $word .= 'a';
        }

        return $word;
    });
}

/**
 * @return array{independentVowels:array<string,string>,consonants:array<string,string>}|null
 */
function naturalReadableBrahmicOverrides(
    ScriptConfig $config,
    BrahmicLanguage $language,
    BrahmicReadableStyle $readableStyle,
): ?array {
    if ($readableStyle !== BrahmicReadableStyle::NATURAL) { return null; }

    $independentVowels = [];
    $consonants = [];

    if ($language !== BrahmicLanguage::SANSKRIT) {
        if ($config === devanagariScriptConfig()) {
            $consonants['ड़'] = 'ḍ';
            $consonants['ढ़'] = 'ḍh';
        } elseif ($config === gujaratiScriptConfig()) {
            $consonants['ડ઼'] = 'ḍ';
            $consonants['ઢ઼'] = 'ḍh';
        }
    }

    if ($config === gujaratiScriptConfig()) {
        $consonants['ળ'] = 'ḻ';
        if ($language === BrahmicLanguage::GUJARATI) {
            $independentVowels['ઋ'] = 'ru';
        }
    } elseif ($config === devanagariScriptConfig()) {
        $consonants['ळ'] = 'ḻ';
        if ($language === BrahmicLanguage::GUJARATI) {
            $independentVowels['ऋ'] = 'ru';
        }
    }

    if ($independentVowels === [] && $consonants === []) { return null; }

    return [
        'independentVowels' => $independentVowels,
        'consonants' => $consonants,
    ];
}

function collapseReadableBrahmicVowelLength(string $text): string
{
    return str_replace(
        ['ā', 'Ā', 'ī', 'Ī', 'ū', 'Ū', 'ē', 'Ē', 'ō', 'Ō', 'ṝ', 'Ṝ', 'ḹ', 'Ḹ'],
        ['a', 'A', 'i', 'I', 'u', 'U', 'e', 'E', 'o', 'O', 'ri', 'Ri', 'li', 'Li'],
        $text,
    );
}

function doubleReadableBrahmicVowelLength(string $text): string
{
    return str_replace(
        ['āī', 'Āī', 'aī', 'Aī', 'ā', 'Ā', 'ī', 'Ī', 'ū', 'Ū', 'ē', 'Ē', 'ō', 'Ō', 'ṝ', 'Ṝ', 'ḹ', 'Ḹ'],
        ['aai', 'Aai', 'ai', 'Ai', 'aa', 'Aa', 'ee', 'Ee', 'oo', 'Oo', 'ee', 'Ee', 'oo', 'Oo', 'ree', 'Ree', 'lee', 'Lee'],
        $text,
    );
}

/** @var array<string,string> */
const LONG_VOWEL_COLLAPSE = [
    'ā' => 'a', 'Ā' => 'A', 'ī' => 'i', 'Ī' => 'I', 'ū' => 'u', 'Ū' => 'U',
    'ē' => 'e', 'Ē' => 'E', 'ō' => 'o', 'Ō' => 'O', 'ṝ' => 'ri', 'Ṝ' => 'Ri',
    'ḹ' => 'li', 'Ḹ' => 'Li',
];

function collapseBrahmicVowelToken(string $text): string
{
    $chars = Unicode::split($text);
    if ($chars === []) { return $text; }

    $short = LONG_VOWEL_COLLAPSE[$chars[0]] ?? null;
    if ($short === null) { return $text; }

    $chars[0] = $short;

    return implode('', $chars);
}

function isLongBrahmicVowelToken(string $text): bool
{
    $chars = Unicode::split($text);

    return $chars !== [] && isset(LONG_VOWEL_COLLAPSE[$chars[0]]);
}

function isCentralLongBrahmicVowel(string $text): bool
{
    $chars = Unicode::split($text);

    return $chars !== [] && ($chars[0] === 'ā' || $chars[0] === 'Ā');
}

/**
 * @param list<array{type:string,text:string}> $tokens
 */
function brahmicCodaWeight(array $tokens): ?int
{
    $weight = 0;
    foreach ($tokens as $token) {
        if ($token['type'] === 'consonant' || $token['type'] === 'sign') {
            ++$weight;
            continue;
        }

        return null;
    }

    return $weight;
}

/**
 * A liquid or glide across a deleted schwa joins the preceding consonant.
 *
 * @param array{type:string,text:string} $token
 */
function isClusterFormingBrahmicResonant(array $token): bool
{
    $base = brahmicBaseConsonant($token);

    return in_array($base, ['r', 'y', 'l', 'v'], true);
}

/**
 * A final open syllable that begins with a nasal is its own clitic.
 *
 * @param array{type:string,text:string} $token
 */
function isNasalBrahmicConsonant(array $token): bool
{
    $base = brahmicBaseConsonant($token);

    return in_array($base, ['m', 'n', 'ṇ', 'ñ', 'ṅ'], true);
}

/**
 * @param list<array{type:string,text:string}> $tokens
 */
function brahmicCodaHasBoundary(array $tokens): bool
{
    foreach ($tokens as $token) {
        if ($token['type'] === 'boundary') { return true; }
    }

    return false;
}

/**
 * @param list<array{type:string,text:string}> $contact
 */
function isLightBrahmicSyllableContact(array $contact): bool
{
    $contact = array_values($contact);
    if (count($contact) === 1 && $contact[0]['type'] === 'consonant') { return true; }

    $at = null;
    $boundaries = 0;
    foreach ($contact as $index => $token) {
        if ($token['type'] !== 'boundary') { continue; }

        ++$boundaries;
        $at = $index;
    }

    if ($boundaries !== 1 || $at === null) { return false; }

    $left = array_slice($contact, 0, $at);
    $right = array_slice($contact, $at + 1);
    if (brahmicCodaWeight($left) !== 1 || brahmicCodaWeight($right) !== 1) { return false; }

    foreach ($right as $token) {
        if ($token['type'] === 'consonant') {
            return !isClusterFormingBrahmicResonant($token);
        }
    }

    return false;
}

function isPeripheralLongBrahmicVowel(string $text): bool
{
    return isLongBrahmicVowelToken($text) && !isCentralLongBrahmicVowel($text);
}

/**
 * @param array{type:string,text:string} $token
 */
function isNasalBrahmicSign(array $token): bool
{
    return $token['type'] === 'sign' && ($token['text'] === 'ṃ' || $token['text'] === 'ṁ');
}

/**
 * हूँगा writes n+g between ū and the next vowel. The nasal is the coda and the
 * stop is the next onset. A stop+liquid pair is one onset (રાત્રે).
 *
 * @param list<array{type:string,text:string}> $tokens
 */
function hasNasalBrahmicCodaBeforeNext(array $tokens, int $vowelIndex, int $nextIndex): bool
{
    $between = array_values(array_slice($tokens, $vowelIndex + 1, $nextIndex - $vowelIndex - 1));
    $consonantAt = [];
    foreach ($between as $index => $token) {
        if ($token['type'] !== 'consonant' && $token['type'] !== 'sign') { return false; }

        if ($token['type'] === 'consonant') { $consonantAt[] = $index; }
    }

    if ($consonantAt === []) { return false; }

    $onset = 1;
    $lastAt = $consonantAt[count($consonantAt) - 1];
    if (count($consonantAt) >= 2) {
        $prevAt = $consonantAt[count($consonantAt) - 2];
        if ($lastAt === $prevAt + 1 && isClusterFormingBrahmicResonant($between[$lastAt])) {
            $onset = 2;
        }
    }

    if (count($between) <= $onset) { return false; }

    $coda = array_values(array_slice($between, 0, count($between) - $onset));
    if (brahmicCodaWeight($coda) !== 1) { return false; }

    return isNasalBrahmicConsonant($coda[0]) || isNasalBrahmicSign($coda[0]);
}

/**
 * @param list<array{type:string,text:string}> $tokens
 *
 * @return array<int,true>
 */
function smartBrahmicLengthKeepIndexes(array $tokens): array
{
    $keep = [];
    if ($tokens === []) { return $keep; }

    // A word-initial long vowel has no consonant to carry it (āj, āvīne, ūpar).
    if ($tokens[0]['type'] === 'vowel' && isLongBrahmicVowelToken($tokens[0]['text'])) {
        $keep[0] = true;
    }

    $vowelIndexes = [];
    foreach ($tokens as $index => $token) {
        if ($token['type'] === 'vowel') { $vowelIndexes[] = $index; }
    }

    if ($vowelIndexes === []) { return $keep; }

    // A nasal coda before the next onset keeps a non-final peripheral vowel.
    $vowelCount = count($vowelIndexes);
    for ($n = 0; $n < $vowelCount - 1; ++$n) {
        $index = $vowelIndexes[$n];
        if (!isPeripheralLongBrahmicVowel($tokens[$index]['text'])) { continue; }

        if (hasNasalBrahmicCodaBeforeNext($tokens, $index, $vowelIndexes[$n + 1])) {
            $keep[$index] = true;
        }
    }

    if (count($vowelIndexes) === 1) {
        $index = $vowelIndexes[0];
        $coda = array_slice($tokens, $index + 1);
        // One coda keeps the vowel (cār, rūp, hūn, dūdh). An open syllable and a
        // true cluster (mārg) both shorten.
        if (
            !brahmicCodaHasBoundary($coda)
            && isLongBrahmicVowelToken($tokens[$index]['text'])
            && brahmicCodaWeight($coda) === 1
        ) {
            $keep[$index] = true;
        }

        return $keep;
    }

    $last = $vowelIndexes[count($vowelIndexes) - 1];
    $previous = $vowelIndexes[count($vowelIndexes) - 2];
    $lastCoda = array_slice($tokens, $last + 1);
    if (count($vowelIndexes) === 2 && isLongBrahmicVowelToken($tokens[$previous]['text'])) {
        $contact = array_slice($tokens, $previous + 1, $last - $previous - 1);
        // Two nuclei and an open second syllable: māre, nīce, cār plus an open syllable.
        if (brahmicCodaWeight($lastCoda) === 0 && isLightBrahmicSyllableContact($contact)) {
            $keep[$previous] = true;
        }
    }

    // The last syllable, closed by one consonant. ā shortens. ī/ū/ē/ō stay.
    if (
        !brahmicCodaHasBoundary($lastCoda)
        && brahmicCodaWeight($lastCoda) === 1
        && isLongBrahmicVowelToken($tokens[$last]['text'])
        && !isCentralLongBrahmicVowel($tokens[$last]['text'])
    ) {
        $keep[$last] = true;
    }

    // A short vowel after one obstruent is a vocalic ending (svarūpe).
    if (
        count($vowelIndexes) > 2
        && !isLongBrahmicVowelToken($tokens[$last]['text'])
        && brahmicCodaWeight($lastCoda) === 0
    ) {
        $contact = array_values(array_slice($tokens, $previous + 1, $last - $previous - 1));
        if (
            count($contact) === 1
            && $contact[0]['type'] === 'consonant'
            && !isClusterFormingBrahmicResonant($contact[0])
            && !isNasalBrahmicConsonant($contact[0])
            && isLongBrahmicVowelToken($tokens[$previous]['text'])
            && !isCentralLongBrahmicVowel($tokens[$previous]['text'])
        ) {
            $keep[$previous] = true;
        }
    }

    return $keep;
}

function withoutBrahmicSyllableBreaks(string $text): string
{
    return str_replace(SYLLABLE_BREAK, '', $text);
}

/**
 * A schwa boundary keeps the consonant before the break on the stem.
 *
 * @param list<array{type:string,text:string}> $tokens
 *
 * @return array{start:int,finalIsLong:bool}|null
 */
function finalOpenBrahmicSyllableSplit(array $tokens): ?array
{
    $vowelIndexes = [];
    foreach ($tokens as $index => $token) {
        if ($token['type'] === 'vowel') { $vowelIndexes[] = $index; }
    }

    if (count($vowelIndexes) < 3) { return null; }

    $last = $vowelIndexes[count($vowelIndexes) - 1];
    $previous = $vowelIndexes[count($vowelIndexes) - 2];
    if (brahmicCodaWeight(array_slice($tokens, $last + 1)) !== 0) { return null; }

    $contact = array_values(array_slice($tokens, $previous + 1, $last - $previous - 1));
    if (!isLightBrahmicSyllableContact($contact)) { return null; }

    $boundaryAt = null;
    foreach ($contact as $index => $token) {
        if ($token['type'] === 'boundary') {
            $boundaryAt = $index;
            break;
        }
    }

    $start = $boundaryAt === null ? $previous + 1 : $previous + 1 + $boundaryAt;

    return [
        'start' => $start,
        'finalIsLong' => isLongBrahmicVowelToken($tokens[$last]['text']),
    ];
}

/**
 * @param list<array{type:string,text:string}> $tokens
 */
function peelSavesPeripheralBrahmic(array $tokens, int $start): bool
{
    $full = smartBrahmicLengthKeepIndexes($tokens);
    $stem = smartBrahmicLengthKeepIndexes(array_values(array_slice($tokens, 0, $start)));
    foreach ($stem as $index => $_) {
        if (isset($full[$index])) { continue; }

        if (isPeripheralLongBrahmicVowel($tokens[$index]['text'])) { return true; }
    }

    return false;
}

/**
 * @param list<array{type:string,text:string}> $tokens
 */
function renderSmartBrahmicLengthTokens(array $tokens): string
{
    $keep = smartBrahmicLengthKeepIndexes($tokens);
    $rendered = '';
    foreach ($tokens as $index => $token) {
        if ($token['type'] === 'boundary') { continue; }

        $rendered .= ($token['type'] === 'vowel' && !isset($keep[$index]))
            ? collapseBrahmicVowelToken($token['text'])
            : $token['text'];
    }

    return $rendered;
}

/**
 * @param list<array{type:string,text:string}> $tokens
 */
function renderCollapsedBrahmicEnding(array $tokens): string
{
    $rendered = '';
    foreach ($tokens as $token) {
        if ($token['type'] === 'boundary') { continue; }

        $rendered .= $token['type'] === 'vowel'
            ? collapseBrahmicVowelToken($token['text'])
            : $token['text'];
    }

    return $rendered;
}

function smartReadableBrahmicVowelLengthWord(string $word, BrahmicLanguage $language): string
{
    if ($language === BrahmicLanguage::SANSKRIT) {
        return doubleReadableBrahmicVowelLength(withoutBrahmicSyllableBreaks($word));
    }

    $stem = tokenizeAnnotatedBrahmicWord($word);
    $ending = '';
    // Long open endings stack. A short ending is removed only once, and only
    // when that keeps a peripheral vowel on the stem.
    while ($stem !== []) {
        $split = finalOpenBrahmicSyllableSplit($stem);
        if ($split === null) { break; }

        $shortOk = !$split['finalIsLong'] && peelSavesPeripheralBrahmic($stem, $split['start']);
        if (!$split['finalIsLong'] && !$shortOk) { break; }

        $ending = renderCollapsedBrahmicEnding(array_values(array_slice($stem, $split['start']))) . $ending;
        $stem = array_values(array_slice($stem, 0, $split['start']));
        if ($shortOk) { break; }
    }

    return doubleReadableBrahmicVowelLength(renderSmartBrahmicLengthTokens($stem) . $ending);
}

function applyReadableBrahmicVowelLength(
    string $text,
    BrahmicVowelLengthPolicy $policy,
    BrahmicLanguage $language = BrahmicLanguage::AUTO,
    BrahmicReadableStyle $readableStyle = BrahmicReadableStyle::LEGACY,
): string {
    if ($readableStyle !== BrahmicReadableStyle::NATURAL) {
        return $policy === BrahmicVowelLengthPolicy::DOUBLE
            ? doubleReadableBrahmicVowelLength($text)
            : $text;
    }

    return match ($policy) {
        BrahmicVowelLengthPolicy::DOUBLE => doubleReadableBrahmicVowelLength(withoutBrahmicSyllableBreaks($text)),
        BrahmicVowelLengthPolicy::COLLAPSE => collapseReadableBrahmicVowelLength(withoutBrahmicSyllableBreaks($text)),
        BrahmicVowelLengthPolicy::SMART => mapBrahmicTranscriptionWords(
            $text,
            static fn(string $word): string => smartReadableBrahmicVowelLengthWord($word, $language),
        ),
    };
}

function applyBrahmicTerminalAnusvara(string $text, TerminalAnusvaraPolicy $policy): string
{
    $replacement = match ($policy) {
        TerminalAnusvaraPolicy::DROP => '',
        TerminalAnusvaraPolicy::M => 'm',
        TerminalAnusvaraPolicy::N => 'n',
    };

    return (string) preg_replace('/[ṃṁ](?=$|[^A-Za-z\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}\x{E101}])/u', $replacement, $text);
}

function applyReadableBrahmicCandrabindu(string $text, TerminalAnusvaraPolicy $policy): string
{
    $replacement = match ($policy) {
        TerminalAnusvaraPolicy::DROP => '',
        TerminalAnusvaraPolicy::M => 'm',
        TerminalAnusvaraPolicy::N => 'n',
    };

    return (string) preg_replace_callback(
        '/([aāiīuūeēoōṛṝḷḹăêĕĭôŏæœ])\x{0310}/iu',
        static fn(array $m): string => $m[1] . $replacement,
        $text,
    );
}

function stripDanglingBrahmicModifiers(string $text): string
{
    return (string) preg_replace('/[़઼्્ૺૻૼ૽૾૿]/u', '', $text);
}

function applyBrahmicAynPolicy(string $text, AynPolicy $policy): string
{
    if ($policy === AynPolicy::PRESERVE) { return $text; }

    return str_replace('ʿ', $policy === AynPolicy::DROP ? '' : "'", $text);
}

/**
 * @param array<string,string> $overrides
 */
function applyBrahmicReadableWordOverrides(string $text, array $overrides): string
{
    if ($overrides === []) { return $text; }

    return mapBrahmicTranscriptionWords(
        $text,
        static fn(string $word): string => array_key_exists($word, $overrides)
            ? (string) $overrides[$word]
            : $word,
    );
}

function capitalizeBrahmicReadableSentenceStarts(string $text): string
{
    $chars = Unicode::split($text);
    $out = [];
    $capitalizeNext = true;

    foreach ($chars as $ch) {
        if ($capitalizeNext && preg_match('/^[a-z]$/', $ch) === 1) {
            $out[] = strtoupper($ch);
            $capitalizeNext = false;
            continue;
        }

        $out[] = $ch;
        if (preg_match('/^[A-Za-z]$/', $ch) === 1) {
            $capitalizeNext = false;
        }

        if (in_array($ch, ['.', '!', '?', '।', '॥', "\n", "\r"], true)) {
            $capitalizeNext = true;
        }
    }

    return implode('', $out);
}

function protectBrahmicSemanticOm(string $text): string
{
    return str_replace(['ॐ', 'ૐ', 'ꣽ'], "\u{E102}", $text);
}

function restoreBrahmicSemanticOm(string $text): string
{
    return str_replace("\u{E102}", 'om', $text);
}

function toPlainEnglishFromBrahmicInternal(
    string $text,
    ScriptConfig $config,
    BrahmicLanguage $scriptLanguage,
    ?BrahmicPlainEnglishOptions $options = null,
    ?PlainEnglishRomanizationProfile $forcedProfile = null,
): string {
    $resolved = $options ?? new BrahmicPlainEnglishOptions;
    $language = $resolved->language === BrahmicLanguage::AUTO ? $scriptLanguage : $resolved->language;
    $protectedLatin = protectSourceLatin($text);
    $annotated = BrahmicToIastConverter::convert(
        protectBrahmicSemanticOm($protectedLatin['text']),
        $config,
        new ScriptToIastOptions(preserveEncodedVedicMarks: $resolved->preserveVedicAccentMarks),
        INHERENT_SCHWA,
        naturalReadableBrahmicOverrides($config, $language, $resolved->readableStyle),
    );
    $iast = stripDanglingBrahmicModifiers(
        naturalizeAnnotatedBrahmicIast(
            $annotated,
            $language,
            $resolved->schwaDeletion,
            $resolved->readableStyle,
        ),
    );
    $naturalBrahmic = $resolved->readableStyle === BrahmicReadableStyle::NATURAL;
    $naturalModernIndoAryan = $naturalBrahmic && $language !== BrahmicLanguage::SANSKRIT;
    if ($naturalBrahmic) {
        $iast = applyBrahmicNaturalMorphology($iast, $language);
    }

    $iast = applyReadableBrahmicCandrabindu($iast, $resolved->terminalAnusvara);
    $iast = applyBrahmicTerminalAnusvara($iast, $resolved->terminalAnusvara);
    $iast = applyReadableBrahmicVowelLength(
        $iast,
        $resolved->vowelLength,
        $language,
        $resolved->readableStyle,
    );

    $profile = $forcedProfile ?? PlainEnglishRomanizationProfile::EXTENDED_INDIC;
    $useW = $resolved->useWForVAfterConsonants ?? false;
    $suppressHunterianAutoW = $profile === PlainEnglishRomanizationProfile::HUNTERIAN
        && ($naturalModernIndoAryan || ($language === BrahmicLanguage::GUJARATI && !$useW));
    if ($suppressHunterianAutoW) {
        $iast = applyHunterianVisargaAbsorption($iast);
    }

    $engineProfile = $suppressHunterianAutoW
        ? PlainEnglishRomanizationProfile::EXTENDED_INDIC
        : $profile;

    $result = toPlainEnglishFromIast($iast, new IastPlainEnglishOptions(
        finalA: FinalAPolicy::KEEP,
        jna: $resolved->jna,
        nya: $resolved->nya,
        profile: $engineProfile,
        glottalStop: $resolved->glottalStop,
        convertCToCh: $resolved->convertCToCh,
        assimilateAnusvara: $resolved->assimilateAnusvara,
        removeAvagraha: $resolved->removeAvagraha,
        collapseWhitespace: $resolved->collapseWhitespace,
        enableInternalSchwaSyncope: false,
        useWForVAfterConsonants: $naturalModernIndoAryan ? false : $useW,
        preserveVedicAccentMarks: $resolved->preserveVedicAccentMarks,
    ));
    $result = applyBrahmicAynPolicy($result, $resolved->ayn);
    $result = restoreBrahmicSemanticOm($result);
    $result = applyBrahmicReadableWordOverrides($result, $resolved->readableWordOverrides);
    if ($resolved->capitalizeSentenceStarts) {
        $result = capitalizeBrahmicReadableSentenceStarts($result);
    }

    $result = restoreSourceLatin($result, $protectedLatin['spans']);

    return $result;
}

function applyHunterianVisargaAbsorption(string $text): string
{
    return (string) preg_replace(
        '/(?:ḥ|Ḥ|h\x{0323}|H\x{0323})([kKgGcCjJtTdDpPbBsSśŚṣṢhH])/u',
        '$1',
        $text,
    );
}

function toPlainEnglishFromDevanagari(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toPlainEnglishFromBrahmicInternal($text, devanagariScriptConfig(), BrahmicLanguage::HINDI, $options);
}

function toHunterianFromDevanagari(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    $resolved = $options ?? new BrahmicPlainEnglishOptions(vowelLength: BrahmicVowelLengthPolicy::COLLAPSE);

    return toPlainEnglishFromBrahmicInternal($text, devanagariScriptConfig(), BrahmicLanguage::HINDI, $resolved, PlainEnglishRomanizationProfile::HUNTERIAN);
}

function toPlainEnglishFromGujarati(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toPlainEnglishFromBrahmicInternal($text, gujaratiScriptConfig(), BrahmicLanguage::GUJARATI, $options);
}

function toHunterianFromGujarati(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    $resolved = $options ?? new BrahmicPlainEnglishOptions(vowelLength: BrahmicVowelLengthPolicy::COLLAPSE);

    return toPlainEnglishFromBrahmicInternal($text, gujaratiScriptConfig(), BrahmicLanguage::GUJARATI, $resolved, PlainEnglishRomanizationProfile::HUNTERIAN);
}

function to_plain_english_from_devanagari(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toPlainEnglishFromDevanagari($text, $options);
}

function to_hunterian_from_devanagari(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toHunterianFromDevanagari($text, $options);
}

function to_plain_english_from_gujarati(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toPlainEnglishFromGujarati($text, $options);
}

function to_hunterian_from_gujarati(string $text, ?BrahmicPlainEnglishOptions $options = null): string
{
    return toHunterianFromGujarati($text, $options);
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
