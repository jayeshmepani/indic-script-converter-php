<?php

declare(strict_types=1);

namespace Lipimala;

require_once __DIR__ . '/Forward.php';

enum GujaratiRomanizationProfile: string
{
    case STRICT_IAST = 'strictIast';
    case ISO_15919_CORE = 'iso15919Core';
    case EXTENDED_INDIC = 'extendedIndic';

    public const strictIast = self::STRICT_IAST;

    public const iso15919Core = self::ISO_15919_CORE;

    public const extendedIndic = self::EXTENDED_INDIC;
}

enum IastToGujaratiUnknownLatinPolicy: string
{
    case PASS_THROUGH = 'passThrough';
    case BRACKET = 'bracket';
    case THROW_ERROR = 'throwError';

    public const passThrough = self::PASS_THROUGH;

    public const bracket = self::BRACKET;

    public const throwError = self::THROW_ERROR;
}

enum IastToGujaratiDigitPolicy: string
{
    case PRESERVE_ASCII = 'preserveAscii';
    case CONVERT_TO_SCRIPT = 'convertToScript';

    public const preserveAscii = self::PRESERVE_ASCII;

    public const convertToScript = self::CONVERT_TO_SCRIPT;
}

enum IastToGujaratiPunctuationPolicy: string
{
    case PRESERVE = 'preserve';
    case INDIC_DANDA = 'indicDanda';

    public const preserve = self::PRESERVE;

    public const indicDanda = self::INDIC_DANDA;
}

enum IastToGujaratiOmPolicy: string
{
    case TRANSLITERATE_LETTERS = 'transliterateLetters';
    case USE_OM_SIGN = 'useOmSign';

    public const transliterateLetters = self::TRANSLITERATE_LETTERS;

    public const useOmSign = self::USE_OM_SIGN;
}

enum IastToGujaratiAmbiguousLPolicy: string
{
    case CONTEXT = 'context';
    case PREFER_VOCALIC = 'preferVocalic';
    case PREFER_CONSONANT = 'preferConsonant';

    public const context = self::CONTEXT;

    public const preferVocalic = self::PREFER_VOCALIC;

    public const preferConsonant = self::PREFER_CONSONANT;
}

final readonly class IastToGujaratiOptions
{
    public function __construct(
        public GujaratiRomanizationProfile $profile = GujaratiRomanizationProfile::EXTENDED_INDIC,
        public IastToGujaratiUnknownLatinPolicy $unknownLatinPolicy = IastToGujaratiUnknownLatinPolicy::PASS_THROUGH,
        public IastToGujaratiDigitPolicy $digitPolicy = IastToGujaratiDigitPolicy::PRESERVE_ASCII,
        public IastToGujaratiPunctuationPolicy $punctuationPolicy = IastToGujaratiPunctuationPolicy::PRESERVE,
        public IastToGujaratiOmPolicy $omPolicy = IastToGujaratiOmPolicy::TRANSLITERATE_LETTERS,
        public IastToGujaratiAmbiguousLPolicy $ambiguousLPolicy = IastToGujaratiAmbiguousLPolicy::CONTEXT,
        public bool $acceptAsciiLongVowels = false,
        public bool $acceptPlainSh = true,
        public bool $acceptPlainXAsKha = true,
        public bool $acceptWAsVa = true,
        public bool $preserveVedicAccentMarks = true,
        public bool $collapseWhitespace = false,
        public bool $embedExactSourceMetadata = false,
    ) {}
}

/**
 * @return array<string,true>
 */
function gujrSet(array $values): array
{
    return array_fill_keys($values, true);
}

function gujaratiForwardConverter(): ForwardConverter
{
    static $converter = null;
    if ($converter instanceof ForwardConverter) {
        return $converter;
    }

    $independentVowels = [
        'a' => 'અ','ā' => 'આ','i' => 'ઇ','ī' => 'ઈ','u' => 'ઉ','ū' => 'ઊ','ṛ' => 'ઋ','ṝ' => 'ૠ','ḷ' => 'ઌ','ḹ' => 'ૡ',
        'e' => 'એ','ē' => 'એ','ai' => 'ઐ','o' => 'ઓ','ō' => 'ઓ','au' => 'ઔ','ă' => 'અ','ĕ' => 'ઍ','ê' => 'ઍ','æ' => 'ઍ',
        'ŏ' => 'ઑ','ô' => 'ઑ','oe' => 'ઓએ','ōe' => 'ઓએ','ooe' => 'ઓએ','aw' => 'ઑ','ue' => 'ઉએ','ūe' => 'ઊએ','uue' => 'ઊએ',
    ];
    $vowelSigns = [
        'a' => '','ā' => 'ા','i' => 'િ','ī' => 'ી','u' => 'ુ','ū' => 'ૂ','ṛ' => 'ૃ','ṝ' => 'ૄ','ḷ' => 'ૢ','ḹ' => 'ૣ',
        'e' => 'ે','ē' => 'ે','ai' => 'ૈ','o' => 'ો','ō' => 'ો','au' => 'ૌ','ă' => '','ĕ' => 'ૅ','ê' => 'ૅ','æ' => 'ૅ',
        'ŏ' => 'ૉ','ô' => 'ૉ','oe' => 'ોએ','ōe' => 'ોએ','ooe' => 'ોએ','aw' => 'ૉ','ue' => 'ુએ','ūe' => 'ૂએ','uue' => 'ૂએ',
    ];
    $consonants = [
        'k' => 'ક','kh' => 'ખ','g' => 'ગ','gh' => 'ઘ','ṅ' => 'ઙ','c' => 'ચ','ch' => 'છ','j' => 'જ','jh' => 'ઝ','ñ' => 'ઞ',
        'ṭ' => 'ટ','ṭh' => 'ઠ','ḍ' => 'ડ','ḍh' => 'ઢ','ṇ' => 'ણ','t' => 'ત','th' => 'થ','d' => 'દ','dh' => 'ધ','n' => 'ન',
        'ŋ' => 'ન','ƞ' => 'ન','p' => 'પ','ph' => 'ફ','b' => 'બ','bh' => 'ભ','m' => 'મ','y' => 'ય','r' => 'ર','l' => 'લ','v' => 'વ','w' => 'વ',
        'ś' => 'શ','sh' => 'શ','ṣ' => 'ષ','s' => 'સ','ṡ' => 'સ','h' => 'હ','ħ' => 'હ','ḫ' => 'ખ઼','ḷ' => 'ળ','ḻ' => 'ળ','ṟ' => 'ર઼','ṙ' => 'ર','ṉ' => 'ન઼',
        'q' => 'ક઼','ḳ' => 'ક઼','ḵh' => 'ખ઼','x' => 'ખ઼','ġ' => 'ગ઼','z' => 'જ઼','ż' => 'જ઼','ẓ' => 'જ઼','ṛ' => 'ડ઼','ṛh' => 'ઢ઼','f' => 'ફ઼','ẏ' => 'ય઼',
        'ž' => 'જ઼','zh' => 'ૹ','ǧ' => 'ગ઼','gg' => 'ગ઼','jj' => 'જ઼','ddd' => 'ડ઼','ɗ' => 'ડ઼','bb' => 'બ઼','ɓ' => 'બ઼','ḍḍ' => 'ડ઼','yy' => 'ય઼','ʔ' => 'ઽ',
        'ṯ' => 'ત઼','ḏ' => 'દ઼','ẖ' => 'હ઼','s̱' => 'સ઼','ẕ' => 'જ઼','g̱' => 'ગ઼','ḵ' => 'ખ઼',
    ];
    $signs = [
        'm̐' => 'ᳪ','̃' => 'ઁ',"\u{0310}" => 'ઁ','ṃ' => 'ં','ṁ' => 'ં','ḥ' => 'ઃ',"'" => 'ઽ','‘' => 'ઽ','’' => 'ઽ','ʼ' => 'ઽ',
        "\u{0301}" => '॑',"\u{0300}" => '॒',"\u{030D}" => '॑',"\u{030E}" => '᳚',"\u{0302}" => '᳚',"\u{0320}" => '॒',
        "\u{0AFA}" => "\u{0AFA}","\u{0AFB}" => "\u{0AFB}","\u{0AFC}" => "\u{0AFC}","\u{0AFD}" => "\u{0AFD}","\u{0AFE}" => "\u{0AFE}","\u{0AFF}" => "\u{0AFF}",
        "\u{0B70}" => '૰',"\u{0AF1}" => '૱',
    ];
    $digits = [];
    for ($i = 0; $i < 10; ++$i) {
        $digits[(string) $i] = Unicode::chr(0x0AE6 + $i);
    }

    $strictVowels = gujrSet(['a','ā','i','ī','u','ū','ṛ','ṝ','ḷ','ḹ','e','ai','o','au']);
    $strictConsonants = gujrSet(['k','kh','g','gh','ṅ','c','ch','j','jh','ñ','ṭ','ṭh','ḍ','ḍh','ṇ','t','th','d','dh','n','p','ph','b','bh','m','y','r','l','v','ś','ṣ','s','h']);

    $config = new ForwardScriptConfig(
        virama: '્', omSign: 'ૐ', danda: '।', doubleDanda: '।।', dottedCircle: '◌',
        independentVowels: $independentVowels, vowelSigns: $vowelSigns,
        consonants: $consonants, signs: $signs, digits: $digits,
        strictIastVowels: $strictVowels, strictIastConsonants: $strictConsonants,
    );
    $converter = new ForwardConverter($config, static fn(): object => new IastToGujaratiOptions);
    return $converter;
}

function toGujaratiFromIast(string $text, ?IastToGujaratiOptions $options = null): string
{
    return gujaratiForwardConverter()->convert($text, $options ?? new IastToGujaratiOptions);
}

function to_gujarati_from_iast(string $text, ?IastToGujaratiOptions $options = null): string
{
    return toGujaratiFromIast($text, $options);
}
