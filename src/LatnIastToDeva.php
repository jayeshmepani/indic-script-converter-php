<?php

declare(strict_types=1);

namespace IndicScriptConverter;

require_once __DIR__ . '/Forward.php';

enum DevanagariRomanizationProfile: string
{
    case STRICT_IAST = 'strictIast';
    case ISO_15919_CORE = 'iso15919Core';
    case EXTENDED_INDIC = 'extendedIndic';

    public const strictIast = self::STRICT_IAST;

    public const iso15919Core = self::ISO_15919_CORE;

    public const extendedIndic = self::EXTENDED_INDIC;
}

enum IastToDevanagariUnknownLatinPolicy: string
{
    case PASS_THROUGH = 'passThrough';
    case BRACKET = 'bracket';
    case THROW_ERROR = 'throwError';

    public const passThrough = self::PASS_THROUGH;

    public const bracket = self::BRACKET;

    public const throwError = self::THROW_ERROR;
}

enum IastToDevanagariDigitPolicy: string
{
    case PRESERVE_ASCII = 'preserveAscii';
    case CONVERT_TO_SCRIPT = 'convertToScript';

    public const preserveAscii = self::PRESERVE_ASCII;

    public const convertToScript = self::CONVERT_TO_SCRIPT;
}

enum IastToDevanagariPunctuationPolicy: string
{
    case PRESERVE = 'preserve';
    case INDIC_DANDA = 'indicDanda';

    public const preserve = self::PRESERVE;

    public const indicDanda = self::INDIC_DANDA;
}

enum IastToDevanagariOmPolicy: string
{
    case TRANSLITERATE_LETTERS = 'transliterateLetters';
    case USE_OM_SIGN = 'useOmSign';

    public const transliterateLetters = self::TRANSLITERATE_LETTERS;

    public const useOmSign = self::USE_OM_SIGN;
}

enum IastToDevanagariAmbiguousLPolicy: string
{
    case CONTEXT = 'context';
    case PREFER_VOCALIC = 'preferVocalic';
    case PREFER_CONSONANT = 'preferConsonant';

    public const context = self::CONTEXT;

    public const preferVocalic = self::PREFER_VOCALIC;

    public const preferConsonant = self::PREFER_CONSONANT;
}

final readonly class IastToDevanagariOptions
{
    public function __construct(
        public DevanagariRomanizationProfile $profile = DevanagariRomanizationProfile::EXTENDED_INDIC,
        public IastToDevanagariUnknownLatinPolicy $unknownLatinPolicy = IastToDevanagariUnknownLatinPolicy::PASS_THROUGH,
        public IastToDevanagariDigitPolicy $digitPolicy = IastToDevanagariDigitPolicy::PRESERVE_ASCII,
        public IastToDevanagariPunctuationPolicy $punctuationPolicy = IastToDevanagariPunctuationPolicy::PRESERVE,
        public IastToDevanagariOmPolicy $omPolicy = IastToDevanagariOmPolicy::TRANSLITERATE_LETTERS,
        public IastToDevanagariAmbiguousLPolicy $ambiguousLPolicy = IastToDevanagariAmbiguousLPolicy::CONTEXT,
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
function devaSet(array $values): array
{
    return array_fill_keys($values, true);
}

function devanagariForwardConverter(): ForwardConverter
{
    static $converter = null;
    if ($converter instanceof ForwardConverter) {
        return $converter;
    }

    $independentVowels = [
        'a' => 'अ','ā' => 'आ','i' => 'इ','ī' => 'ई','u' => 'उ','ū' => 'ऊ','ṛ' => 'ऋ','ṝ' => 'ॠ','ḷ' => 'ऌ','ḹ' => 'ॡ',
        'e' => 'ए','ē' => 'ए','ai' => 'ऐ','o' => 'ओ','ō' => 'ओ','au' => 'औ','ă' => 'ऄ','ĕ' => 'ऎ','ê' => 'ऍ','ĕ' => 'ऎ',
        'æ' => 'ॲ','ŏ' => 'ऒ','ô' => 'ऑ','ŏ' => 'ऒ','oe' => 'ॳ','ōe' => 'ॴ','ooe' => 'ॴ','aw' => 'ॵ','ue' => 'ॶ','ūe' => 'ॷ','uue' => 'ॷ',
    ];
    $vowelSigns = [
        'a' => '','ā' => 'ा','i' => 'ि','ī' => 'ी','u' => 'ु','ū' => 'ू','ṛ' => 'ृ','ṝ' => 'ॄ','ḷ' => 'ॢ','ḹ' => 'ॣ',
        'e' => 'े','ē' => 'े','ai' => 'ै','o' => 'ो','ō' => 'ो','au' => 'ौ','ă' => '','ĕ' => 'ॆ','ê' => 'ॅ','ĕ' => 'ॆ',
        'æ' => 'ॅ','ŏ' => 'ॊ','ô' => 'ॉ','ŏ' => 'ॊ','oe' => 'ऺ','ōe' => 'ऻ','ooe' => 'ऻ','aw' => 'ॏ','ue' => 'ॖ','ūe' => 'ॗ','uue' => 'ॗ',
    ];
    $consonants = [
        'k' => 'क','kh' => 'ख','g' => 'ग','gh' => 'घ','ṅ' => 'ङ','c' => 'च','ch' => 'छ','j' => 'ज','jh' => 'झ','ñ' => 'ञ',
        'ṭ' => 'ट','ṭh' => 'ठ','ḍ' => 'ड','ḍh' => 'ढ','ṇ' => 'ण','t' => 'त','th' => 'थ','d' => 'द','dh' => 'ध','n' => 'न',
        'ŋ' => 'न','ƞ' => 'न','p' => 'प','ph' => 'फ','b' => 'ब','bh' => 'भ','m' => 'म','y' => 'य','r' => 'र','l' => 'ल','v' => 'व','w' => 'व',
        'ś' => 'श','sh' => 'श','ṣ' => 'ष','s' => 'स','ṡ' => 'स','h' => 'ह','ħ' => 'ह','ḫ' => 'ख़','ḷ' => 'ळ','ḻ' => 'ऴ','ṟ' => 'ऱ','ṙ' => 'र','ṉ' => 'ऩ',
        'q' => 'क़','ḳ' => 'क़','ḵh' => 'ख़','x' => 'ख़','ġ' => 'ग़','z' => 'ज़','ż' => 'ज़','ẓ' => 'ज़','ṛ' => 'ड़','ṛh' => 'ढ़','f' => 'फ़','ẏ' => 'य़',
        'ž' => 'ज़','zh' => 'ॹ','ǧ' => 'ॻ','gg' => 'ॻ','jj' => 'ॼ','ddd' => 'ॾ','ɗ' => 'ॾ','bb' => 'ॿ','ɓ' => 'ॿ','ḍḍ' => 'ॸ','yy' => 'ॺ','ʔ' => 'ॽ',
        'ṯ' => 'त़','ḏ' => 'द़','ẖ' => 'ह़','s̱' => 'स़','ẕ' => 'ज़','g̱' => 'ग़','ḵ' => 'ख़',
    ];
    $signs = [
        'm̐' => 'ᳪ','̃' => 'ँ',"\u{0310}" => 'ँ','ṃ' => 'ं','ṁ' => 'ं','ḥ' => 'ः',"'" => 'ऽ','‘' => 'ऽ','’' => 'ऽ','ʼ' => 'ऽ',
        "\u{0301}" => '॑',"\u{0300}" => '॒',"\u{030D}" => '॑',"\u{030E}" => '᳚',"\u{0302}" => '᳚',"\u{0320}" => '॒',
        "\u{0900}" => 'ऀ',"\u{0970}" => '॰',"\u{0971}" => 'ॱ',
    ];
    $digits = [];
    for ($i = 0; $i < 10; ++$i) {
        $digits[(string) $i] = Unicode::chr(0x0966 + $i);
    }

    $strictVowels = devaSet(['a','ā','i','ī','u','ū','ṛ','ṝ','ḷ','ḹ','e','ai','o','au']);
    $strictConsonants = devaSet(['k','kh','g','gh','ṅ','c','ch','j','jh','ñ','ṭ','ṭh','ḍ','ḍh','ṇ','t','th','d','dh','n','p','ph','b','bh','m','y','r','l','v','ś','ṣ','s','h']);

    $config = new ForwardScriptConfig(
        virama: '्', omSign: 'ॐ', danda: '।', doubleDanda: '।।', dottedCircle: '◌',
        independentVowels: $independentVowels, vowelSigns: $vowelSigns,
        consonants: $consonants, signs: $signs, digits: $digits,
        strictIastVowels: $strictVowels, strictIastConsonants: $strictConsonants,
    );
    $converter = new ForwardConverter($config, static fn(): object => new IastToDevanagariOptions);
    return $converter;
}

function toDevanagariFromIast(string $text, ?IastToDevanagariOptions $options = null): string
{
    return devanagariForwardConverter()->convert($text, $options ?? new IastToDevanagariOptions);
}

function to_devanagari_from_iast(string $text, ?IastToDevanagariOptions $options = null): string
{
    return toDevanagariFromIast($text, $options);
}
