<?php

declare(strict_types=1);

namespace IndicScriptConverter;

require_once __DIR__ . '/TransliterationCore.php';

enum FinalAPolicy: string
{
    case KEEP = 'keep';
    case DROP = 'drop';
    case SMART = 'smart';

    public const keep = self::KEEP;

    public const drop = self::DROP;

    public const smart = self::SMART;
}

enum JnaPolicy: string
{
    case GYA = 'gya';
    case JNYA = 'jnya';
    case JNA = 'jna';

    public const gya = self::GYA;

    public const jnya = self::JNYA;

    public const jna = self::JNA;
}

enum NyaPolicy: string
{
    case NA = 'na';
    case NYA = 'nya';
    case GNA = 'gna';

    public const na = self::NA;

    public const nya = self::NYA;

    public const gna = self::GNA;
}

enum PlainEnglishRomanizationProfile: string
{
    case STRICT_IAST = 'strictIast';
    case EXTENDED_INDIC = 'extendedIndic';
    case HUNTERIAN = 'hunterian';

    public const strictIast = self::STRICT_IAST;

    public const extendedIndic = self::EXTENDED_INDIC;

    public const hunterian = self::HUNTERIAN;
}

enum GlottalStopPolicy: string
{
    case REMOVE = 'remove';
    case APOSTROPHE = 'apostrophe';

    public const remove = self::REMOVE;

    public const apostrophe = self::APOSTROPHE;
}

final readonly class IastPlainEnglishOptions
{
    /** @param array<string,true>|list<string> $keepFinalAForWords */
    public function __construct(
        public FinalAPolicy $finalA = FinalAPolicy::SMART,
        public JnaPolicy $jna = JnaPolicy::GYA,
        public NyaPolicy $nya = NyaPolicy::NA,
        public PlainEnglishRomanizationProfile $profile = PlainEnglishRomanizationProfile::EXTENDED_INDIC,
        public GlottalStopPolicy $glottalStop = GlottalStopPolicy::REMOVE,
        public bool $convertCToCh = true,
        public bool $assimilateAnusvara = true,
        public bool $removeAvagraha = true,
        public bool $collapseWhitespace = false,
        public bool $enableInternalSchwaSyncope = false,
        public bool $useWForVAfterConsonants = false,
        public bool $preserveVedicAccentMarks = false,
        public array $keepFinalAForWords = [],
    ) {}

    /** @return array<string,true> */
    public function keepFinalASet(): array
    {
        if ($this->keepFinalAForWords === []) { return []; }

        if (array_is_list($this->keepFinalAForWords)) {
            return array_fill_keys(array_map(strval(...), $this->keepFinalAForWords), true);
        }

        return $this->keepFinalAForWords;
    }
}

final class PlainEnglishConverter
{
    private const string PH_CHH = "\u{E010}";

    private const string PH_CHH_CAP = "\u{E011}";

    private const string PH_CHH_ALL = "\u{E012}";

    /** @var array<string,true> */
    private const array VOWELS = ['a' => true,'e' => true,'i' => true,'o' => true,'u' => true];

    private function __construct() {}

    public static function convert(string $inputText, IastPlainEnglishOptions $options): string
    {
        if ($inputText === '') { return $inputText; }

        $text = $inputText;
        if ($options->removeAvagraha) {
            $text = (string) preg_replace('/[ऽ’‘ʼʹ]/u', '', $text);
            $text = (string) preg_replace_callback(
                "/([A-Za-z\\x{00C0}-\\x{00FF}\\x{0100}-\\x{024F}\\x{0250}-\\x{02FF}\\x{1E00}-\\x{1EFF}])'(?=[A-Za-z\\x{00C0}-\\x{00FF}\\x{0100}-\\x{024F}\\x{0250}-\\x{02FF}\\x{1E00}-\\x{1EFF}])/u",
                static fn(array $m): string => $m[1],
                $text,
            );
        }

        $chars = Unicode::split($text);
        $out = [];
        $i = 0;
        while ($i < count($chars)) {
            $ch = $chars[$i];
            if (!$options->preserveVedicAccentMarks && isEncodedVedicMark($ch)) {
                ++$i;
                continue;
            }

            if (self::isLatinChar($ch)) {
                $start = $i++;
                while ($i < count($chars) && self::isLatinChar($chars[$i])) { ++$i; }

                $out[] = self::convertLatinWord(implode('', array_slice($chars, $start, $i - $start)), $options);
                continue;
            }

            $out[] = $ch;
            ++$i;
        }

        $result = implode('', $out);
        if ($options->collapseWhitespace) {
            return trim((string) preg_replace('/\s+/u', ' ', $result));
        }

        return $result;
    }

    private static function isLatinChar(string $ch): bool
    {
        $cp = Unicode::ord($ch);
        return ($cp >= 0x41 && $cp <= 0x5A)
            || ($cp >= 0x61 && $cp <= 0x7A)
            || ($cp >= 0x00C0 && $cp <= 0x00FF)
            || ($cp >= 0x0100 && $cp <= 0x024F)
            || ($cp >= 0x0250 && $cp <= 0x02FF)
            || ($cp >= 0x1E00 && $cp <= 0x1EFF)
            || isUnicodeCombiningMark($ch);
    }

    private static function convertLatinWord(string $word, IastPlainEnglishOptions $options): string
    {
        if ($word === '') { return $word; }

        $isAllUpper = self::isAllUpperWord($word);
        $chars = Unicode::split($word);
        $last = $chars[array_key_last($chars)];
        $endsInShortA = $last === 'a' || $last === 'A';
        $keepFinalNoisy = self::containsNoisyLatinLetter($word);

        $w = normalizeUnicode($word, UnicodeNormalizationForm::NFD);
        $w = self::applyProfile($w, $options->profile);
        $w = self::applyJna($w, $options->jna);
        if ($options->assimilateAnusvara) {
            $w = self::resolveAnusvara($w);
        } else {
            $w = (string) preg_replace('/[ṃṁ]/u', 'm', $w);
            $w = (string) preg_replace('/[ṂṀ]/u', 'M', $w);
        }

        if ($options->convertCToCh) { $w = self::expandC($w); }

        if ($options->enableInternalSchwaSyncope && Unicode::length($w) > 5) {
            $w = self::applyInternalSchwaSyncope($w);
        }

        $w = self::foldLetters($w, $options->glottalStop, $options->nya, $options->preserveVedicAccentMarks);

        if ($options->useWForVAfterConsonants || $options->profile === PlainEnglishRomanizationProfile::HUNTERIAN) {
            $w = (string) preg_replace_callback(
                '/([^aeiou\s])v([aāiīuūeēoō])/iu',
                static fn(array $m): string => $m[1] . 'w' . $m[2],
                $w,
            );
        }

        if ($options->finalA !== FinalAPolicy::KEEP && $endsInShortA && !$keepFinalNoisy) {
            $w = self::applyFinalARule($w, $options);
        }

        return $isAllUpper ? Unicode::upper($w) : $w;
    }

    private static function isAllUpperWord(string $word): bool
    {
        $cased = 0;
        $upper = 0;
        foreach (Unicode::split($word) as $ch) {
            if (isUnicodeCombiningMark($ch)) { continue; }

            $up = Unicode::upper($ch);
            $lo = Unicode::lower($ch);
            if ($up === $lo) { continue; }

            ++$cased;
            if ($ch === $up) { ++$upper; }
        }

        return $cased > 1 && $cased === $upper;
    }

    private static function containsNoisyLatinLetter(string $word): bool
    {
        $noisy = array_fill_keys(Unicode::split('çÇãÃïÏœŒøØłŁßþÞðÐ'), true);
        foreach (Unicode::split($word) as $ch) {
            if (isset($noisy[$ch])) { return true; }
        }

        return false;
    }

    private static function applyProfile(string $word, PlainEnglishRomanizationProfile $profile): string
    {
        $word = str_replace(['x','X',"h\u{032E}","H\u{032E}"], ['kh','Kh','kh','Kh'], $word);
        if ($profile === PlainEnglishRomanizationProfile::STRICT_IAST) { return $word; }

        $word = (string) preg_replace('/ṛh(?=[aāiīuūeêĕoôŏy])/u', 'rh', $word);
        $word = (string) preg_replace('/Ṛh(?=[aāiīuūeêĕoôŏy])/u', 'Rh', $word);
        $word = (string) preg_replace('/ṛ(?=[aāiīuūeêĕoôŏy])/u', 'r', $word);
        $word = (string) preg_replace('/Ṛ(?=[aāiīuūeêĕoôŏy])/u', 'R', $word);
        if ($profile === PlainEnglishRomanizationProfile::HUNTERIAN) {
            $cls = '[kKgGcCjJtTdDpPbBsSśŚṣṢhH]';
            foreach (['ḥ','Ḥ',"h\u{0323}","H\u{0323}"] as $prefix) {
                $pattern = '/' . preg_quote($prefix, '/') . '(' . $cls . ')/u';
                $word = (string) preg_replace_callback($pattern, static fn(array $m): string => $m[1], $word);
            }
        }

        return $word;
    }

    private static function applyInternalSchwaSyncope(string $word): string
    {
        if (Unicode::length($word) <= 5) { return $word; }

        $segments = [];
        $current = '';
        $parsingVowel = null;
        foreach (Unicode::split($word) as $ch) {
            $cp = Unicode::ord($ch);
            $isMark = $cp >= 0x0300 && $cp <= 0x036F;
            $isV = $isMark && $parsingVowel !== null ? $parsingVowel : self::isIastVowelChar($ch);
            if ($parsingVowel === null) {
                $parsingVowel = $isV;
                $current = $ch;
            } elseif ($parsingVowel === $isV) {
                $current .= $ch;
            } else {
                $segments[] = new PlainEnglishSegment($current, (bool) $parsingVowel);
                $current = $ch;
                $parsingVowel = $isV;
            }
        }

        if ($current !== '' && $parsingVowel !== null) {
            $segments[] = new PlainEnglishSegment($current, (bool) $parsingVowel);
        }

        $vis = [];
        foreach ($segments as $idx => $seg) { if ($seg->isVowel) { $vis[] = $idx; } }

        if (count($vis) < 3) { return $word; }

        for ($k = count($vis) - 2; $k >= 1; --$k) {
            $idx = $vis[$k];
            $cand = $segments[$idx];
            if (!self::isShortASchwa($cand->text) || $idx - 1 < 0 || $idx + 1 >= count($segments)) { continue; }

            $prev = $segments[$idx - 1];
            $next = $segments[$idx + 1];
            if (self::countConsonants($prev->text) + self::countConsonants($next->text) > 2) { continue; }

            $cand->text = '';
            $prev->text .= $next->text;
            $next->text = '';
        }

        return implode('', array_map(static fn(PlainEnglishSegment $s): string => $s->text, $segments));
    }

    private static function isIastVowelChar(string $ch): bool
    {
        return in_array(Unicode::lower($ch), ['a','ā','i','ī','u','ū','e','o','ṛ','ṝ','ḷ','ḹ','æ','œ'], true);
    }

    private static function isShortASchwa(string $text): bool
    {
        return Unicode::lower((string) preg_replace('/[\x{0300}-\x{036F}]/u', '', $text)) === 'a';
    }

    private static function countConsonants(string $cluster): int
    {
        $s = Unicode::lower($cluster);
        $s = str_replace(['kh','gh','ch','jh','th','dh','ph','bh','sh','zh'], ['K','G','C','J','T','D','P','B','S','Z'], $s);
        return Unicode::length($s);
    }

    private static function applyJna(string $word, JnaPolicy $policy): string
    {
        $reps = match ($policy) {
            JnaPolicy::GYA => ['jñ' => 'gy','Jñ' => 'Gy','JÑ' => 'GY','jÑ' => 'gy',"jn\u{0303}" => 'gy',"Jn\u{0303}" => 'Gy',"JN\u{0303}" => 'GY'],
            JnaPolicy::JNYA => ['jñ' => 'jny','Jñ' => 'Jny','JÑ' => 'JNY','jÑ' => 'jny',"jn\u{0303}" => 'jny',"Jn\u{0303}" => 'Jny',"JN\u{0303}" => 'JNY'],
            JnaPolicy::JNA => ['jñ' => 'jn','Jñ' => 'Jn','JÑ' => 'JN','jÑ' => 'jn',"jn\u{0303}" => 'jn',"Jn\u{0303}" => 'Jn',"JN\u{0303}" => 'JN'],
        };
        return strtr($word, $reps);
    }

    private static function resolveAnusvara(string $word): string
    {
        $pattern = '/([mM](?:\x{0307}|\x{0323}|\x{0310})|[ṃṁṂṀ])(.?)/us';
        return (string) preg_replace_callback($pattern, static function (array $m): string {
            $marker = $m[1];
            $next = ($m[2] ?? '') !== '' ? $m[2] : null;
            $isUpper = Unicode::upper($marker) === $marker && Unicode::lower($marker) !== $marker;
            // preg_replace_callback gives byte offset, so detect initial marker by
            // testing whether this exact match starts the string in the callback.
            // We attach PREG_OFFSET_CAPTURE through a second implementation below.
            $nasal = self::anusvaraNasal($next);
            return ($isUpper ? Unicode::upper($nasal) : Unicode::lower($nasal)) . ($next ?? '');
        }, self::resolveInitialCandrabindu($word));
    }

    private static function resolveInitialCandrabindu(string $word): string
    {
        $chars = Unicode::split($word);
        if (count($chars) >= 2 && ($chars[0] === 'm' || $chars[0] === 'M') && $chars[1] === "\u{0310}") {
            array_splice($chars, 1, 1);
            return implode('', $chars);
        }

        return $word;
    }

    private static function anusvaraNasal(?string $next): string
    {
        if ($next === null) { return 'm'; }

        $lower = Unicode::lower($next);
        if (in_array($lower, ['k','g','c','j','ṭ','ḍ','t','d','n','ṇ','ṅ','ñ'], true)) { return 'n'; }

        if (in_array($lower, ['p','b','m'], true)) { return 'm'; }

        if (in_array($lower, ['ś','ṣ','s','h','y','v'], true)) { return 'n'; }

        return 'm';
    }

    private static function expandC(string $word): string
    {
        $w = str_replace(['ch','Ch','CH'], [self::PH_CHH,self::PH_CHH_CAP,self::PH_CHH_ALL], $word);
        $w = (string) preg_replace_callback('/[cC]/', static fn(array $m): string => $m[0] === 'C' ? 'Ch' : 'ch', $w);
        return str_replace([self::PH_CHH,self::PH_CHH_CAP,self::PH_CHH_ALL], ['chh','Chh','CHH'], $w);
    }

    private static function isEnglishVowel(?string $ch): bool
    {
        return $ch !== null && isset(self::VOWELS[Unicode::lower($ch)]);
    }

    private static function isVedicAccentRune(int $cp): bool
    {
        return isEncodedVedicMark($cp) || in_array($cp, [0x0301,0x0300,0x030D,0x030E,0x0302,0x0329,0x0331,0x0320], true);
    }

    private static function foldLetters(string $word, GlottalStopPolicy $glottal, NyaPolicy $nya, bool $preserveVedic): string
    {
        $chars = Unicode::split($word);
        $out = [];
        $i = 0;
        $prev = null;
        while ($i < count($chars)) {
            $base = $chars[$i++];
            $marks = [];
            while ($i < count($chars) && isUnicodeCombiningMark($chars[$i])) { $marks[] = Unicode::ord($chars[$i++]); }

            $nextBase = $i < count($chars) ? $chars[$i] : null;
            $folded = self::foldMarkedBase($base, $marks, $glottal, $nya, $nextBase, $prev);
            if ($preserveVedic && self::isVowelOutput($folded)) {
                foreach ($marks as $cp) { if (self::isVedicAccentRune($cp)) { $folded .= Unicode::chr($cp); } }
            }

            $out[] = $folded;
            $foldedChars = Unicode::split($folded);
            $prev = $foldedChars === [] ? null : $foldedChars[array_key_last($foldedChars)];
        }

        return implode('', $out);
    }

    private static function isVowelOutput(string $folded): bool
    {
        if ($folded === '') { return false; }

        $chars = Unicode::split($folded);
        $first = Unicode::lower($chars[0]);
        return isset(self::VOWELS[$first]) || in_array(Unicode::lower($folded), ['ri','li'], true);
    }

    /** @param list<int> $marks */
    private static function foldMarkedBase(string $base, array $marks, GlottalStopPolicy $glottal, NyaPolicy $nya, ?string $nextBase, ?string $prev): string
    {
        if ($marks === []) { return self::foldPrecomposed($base, $glottal, $nya, $nextBase, $prev); }

        $foldedBase = self::foldPrecomposed($base, $glottal, $nya, $nextBase, $prev);
        $lower = Unicode::lower($foldedBase);
        $isUpper = Unicode::upper($foldedBase) === $foldedBase && Unicode::lower($foldedBase) !== $foldedBase;
        $hasDotBelow = in_array(0x0323, $marks, true);
        $hasRingBelow = in_array(0x0325, $marks, true);
        $hasDotAbove = in_array(0x0307, $marks, true);
        $hasAcute = in_array(0x0301, $marks, true);
        $hasNasal = in_array(0x0303, $marks, true) || in_array(0x0310, $marks, true);
        $out = null;
        if (isset(self::VOWELS[$lower])) {
            $out = $lower;
            if ($hasNasal && $nextBase === null && self::isLongA($base, $marks)) { $out = 'aa'; }

            if ($hasNasal && self::nasalizedVowelNeedsN($nextBase, $prev)) { $out .= 'n'; }
        } elseif ($lower === 'r') {
            if ($hasRingBelow) { $out = 'ri'; } elseif ($hasDotBelow) { $out = self::isEnglishVowel($prev) ? 'r' : 'ri'; }
        } elseif ($lower === 'l' && ($hasDotBelow || $hasRingBelow)) {
            $out = self::isVowelBase($nextBase, $prev) ? 'l' : 'li';
        } elseif ($lower === 's' && ($hasAcute || $hasDotBelow)) {
            $out = 'sh';
        } elseif ($lower === 't' && $hasDotBelow) {
            $out = 't';
        } elseif ($lower === 'd' && $hasDotBelow) {
            $out = 'd';
        } elseif ($lower === 'n') {
            if ($hasDotAbove || $hasDotBelow) { $out = 'n'; }

            if ($hasNasal) { $out = self::foldNya($nya); }
        } elseif ($lower === 'h' && $hasDotBelow) {
            $out = 'h';
        } elseif ($lower === 'm' && ($hasDotAbove || $hasDotBelow || $hasNasal)) {
            $out = 'm';
        }

        $out ??= self::foldPrecomposed($base, $glottal, $nya, $nextBase, $prev);
        return self::matchCase($isUpper, $out);
    }

    private static function nasalizedVowelNeedsN(?string $nextBase, ?string $prev): bool
    {
        if ($nextBase === null) { return false; }

        $next = Unicode::lower(self::foldPrecomposed($nextBase, GlottalStopPolicy::REMOVE, NyaPolicy::NA, null, $prev));
        if ($next === '' || isset(self::VOWELS[$next])) { return false; }

        $chars = Unicode::split($next);
        return !in_array($chars[0], ['m','n'], true);
    }

    /** @param list<int> $marks */
    private static function isLongA(string $base, array $marks): bool
    {
        return $base === 'ā' || $base === 'Ā' || (Unicode::lower($base) === 'a' && in_array(0x0304, $marks, true));
    }

    private static function isVowelBase(?string $base, ?string $prev): bool
    {
        if ($base === null) { return false; }

        $folded = Unicode::lower(self::foldPrecomposed($base, GlottalStopPolicy::REMOVE, NyaPolicy::NA, null, $prev));
        if ($folded === '') { return false; }

        $chars = Unicode::split($folded);
        return isset(self::VOWELS[$chars[0]]);
    }

    private static function foldNya(NyaPolicy $policy): string
    {
        return match ($policy) { NyaPolicy::NA => 'n', NyaPolicy::NYA => 'ny', NyaPolicy::GNA => 'gn' };
    }

    private static function matchCase(bool $isUpper, string $lower): string
    {
        if (!$isUpper || $lower === '') { return $lower; }

        $chars = Unicode::split($lower);
        $chars[0] = Unicode::upper($chars[0]);
        return implode('', $chars);
    }

    private static function foldPrecomposed(string $ch, GlottalStopPolicy $glottal, NyaPolicy $nya, ?string $nextBase = null, ?string $prev = null): string
    {
        if ($ch === 'ā') { return 'a'; } if ($ch === 'Ā') { return 'A'; }

        if ($ch === 'ī') { return 'i'; } if ($ch === 'Ī') { return 'I'; }

        if ($ch === 'ū') { return 'u'; } if ($ch === 'Ū') { return 'U'; }

        $groups = [
            'a' => 'ăàáâãäå','A' => 'ĂÀÁÂÃÄÅ','i' => 'ìíîï','I' => 'ÌÍÎÏ',
            'e' => 'ĕěēèéêë','E' => 'ĔĚĒÈÉÊË','o' => 'ŏòóôõöōø','O' => 'ŌŎÒÓÔÕÖØ',
            'u' => 'ùúûü','U' => 'ÙÚÛÜ',
        ];
        foreach ($groups as $out => $chars) { if (in_array($ch, Unicode::split($chars), true)) { return $out; } }

        if (in_array($ch, ['Æ','Ǣ'], true)) { return 'Ae'; }

        if ($ch === 'Œ') { return 'Oe'; }

        if (in_array($ch, ['æ','ǣ'], true)) { return 'ae'; }

        if ($ch === 'œ') { return 'oe'; }

        if (in_array($ch, ['ṛ','ṝ'], true)) { return self::isEnglishVowel($prev) ? 'r' : 'ri'; }

        if (in_array($ch, ['Ṛ','Ṝ'], true)) { return self::isEnglishVowel($prev) ? 'R' : 'Ri'; }

        if ($ch === 'ḷ') { return self::isVowelBase($nextBase, $prev) ? 'l' : 'li'; }

        if ($ch === 'Ḷ') { return self::isVowelBase($nextBase, $prev) ? 'L' : 'Li'; }

        if ($ch === 'ḹ') { return 'li'; } if ($ch === 'Ḹ') { return 'Li'; }

        if ($ch === 'ñ') { return self::foldNya($nya); }

        if ($ch === 'Ñ') { return self::matchCase(true, self::foldNya($nya)); }

        if ($ch === 'ʔ') { return $glottal === GlottalStopPolicy::REMOVE ? '' : "'"; }

        static $table = [
            'ṅ' => 'n','Ṅ' => 'N','ŋ' => 'n','Ŋ' => 'N','ƞ' => 'n','Ƞ' => 'N','ṇ' => 'n','Ṇ' => 'N','ṉ' => 'n','Ṉ' => 'N','ṙ' => 'r','Ṙ' => 'R',
            'ç' => 'c','Ç' => 'C','ł' => 'l','Ł' => 'L','ß' => 'ss','þ' => 'th','Þ' => 'Th','ð' => 'd','Ð' => 'D',
            'ṭ' => 't','Ṭ' => 'T','ṯ' => 't','Ṯ' => 'T','ḳ' => 'k','ḵ' => 'k','Ḳ' => 'K','Ḵ' => 'K','ḍ' => 'd','Ḍ' => 'D','ḏ' => 'd','Ḏ' => 'D',
            'ś' => 'sh','Ś' => 'Sh','ṣ' => 'sh','Ṣ' => 'Sh','ṡ' => 's','Ṡ' => 'S','ž' => 'zh','Ž' => 'Zh','ź' => 'z','ż' => 'z','Ź' => 'Z','Ż' => 'Z','ẓ' => 'z','Ẓ' => 'Z',
            'ẏ' => 'y','Ẏ' => 'Y','ḻ' => 'l','Ḻ' => 'L','ṟ' => 'r','Ṟ' => 'R','ġ' => 'g','ǧ' => 'g','Ġ' => 'G','Ǧ' => 'G','ɓ' => 'b','Ɓ' => 'B','ɗ' => 'd','Ɗ' => 'D',
            'ḥ' => 'h','Ḥ' => 'H','ħ' => 'h','ḫ' => 'h','ẖ' => 'h','Ħ' => 'H','Ḫ' => 'H','H̱' => 'H','ṃ' => 'm','Ṃ' => 'M','ṁ' => 'm','Ṁ' => 'M',
        ];
        return $table[$ch] ?? $ch;
    }

    private static function applyFinalARule(string $word, IastPlainEnglishOptions $options): string
    {
        $chars = Unicode::split($word);
        if (count($chars) <= 2) { return $word; }

        $lower = Unicode::lower($word);
        $lowerChars = Unicode::split($lower);
        if ($lowerChars[array_key_last($lowerChars)] !== 'a') { return $word; }

        if (isset(self::VOWELS[$lowerChars[count($lowerChars) - 2]])) { return $word; }

        if ($options->finalA === FinalAPolicy::DROP) { return implode('', array_slice($chars, 0, -1)); }

        $keepSet = $options->keepFinalASet();
        if (isset($keepSet[$lower])) { return $word; }

        foreach (['moksha','vriksha','ashvattha','simha','sinha'] as $ending) { if (str_ends_with($lower, $ending)) { return $word; } }

        if (str_ends_with($lower, 'ya')) { return $word; }

        if (str_ends_with($lower, 'ha') && count($lowerChars) >= 3 && isset(self::VOWELS[$lowerChars[count($lowerChars) - 3]])) { return $word; }

        $without = implode('', array_slice($chars, 0, -1));
        return self::leavesAwkwardFinalCluster($without) ? $word : $without;
    }

    private static function leavesAwkwardFinalCluster(string $word): bool
    {
        $normalized = self::normalizeFinalCluster(Unicode::lower($word));
        $last = -1;
        foreach (Unicode::split($normalized) as $i => $ch) { if (isset(self::VOWELS[$ch])) { $last = $i; } }

        $chars = Unicode::split($normalized);
        $suffix = implode('', $last >= 0 ? array_slice($chars, $last + 1) : $chars);
        $len = Unicode::length($suffix);
        if ($len <= 1) { return false; }

        if ($suffix === 'ng') { return false; }

        if ($len >= 3) { return true; }

        $bad = array_fill_keys(['tr','dr','gy','kr','gr','jr','rm','hm','ry','ly','ny','my','sv','dv','tv','pn','bn','kn','gn','km','gm','pm','bm','tm','dm','dD','hX'], true);
        if (isset($bad[$suffix])) { return true; }

        return preg_match('/[CKSDTGHPBX][mnlrvy]$/', $suffix) === 1;
    }

    private static function normalizeFinalCluster(string $word): string
    {
        return str_replace(['ksh','chh','ch','sh','gh','dh','th','ph','bh','kh'], ['K','H','C','S','G','D','T','P','B','X'], $word);
    }
}

final class PlainEnglishSegment
{
    public function __construct(public string $text, public readonly bool $isVowel) {}
}

function toPlainEnglishFromIast(string $text, ?IastPlainEnglishOptions $options = null): string
{
    return PlainEnglishConverter::convert($text, $options ?? new IastPlainEnglishOptions);
}

function to_plain_english_from_iast(string $text, ?IastPlainEnglishOptions $options = null): string
{
    return toPlainEnglishFromIast($text, $options);
}
