<?php

declare(strict_types=1);

namespace Lipimala;

use BackedEnum;
use Closure;
use InvalidArgumentException;

require_once __DIR__ . '/TransliterationCore.php';

final readonly class ForwardScriptConfig
{
    /**
     * @param array<string,string> $independentVowels
     * @param array<string,string> $vowelSigns
     * @param array<string,string> $consonants
     * @param array<string,string> $signs
     * @param array<string,string> $digits
     * @param array<string,true> $strictIastVowels
     * @param array<string,true> $strictIastConsonants
     */
    public function __construct(
        public string $virama,
        public string $omSign,
        public string $danda,
        public string $doubleDanda,
        public string $dottedCircle,
        public array $independentVowels,
        public array $vowelSigns,
        public array $consonants,
        public array $signs,
        public array $digits,
        public array $strictIastVowels,
        public array $strictIastConsonants,
    ) {}
}

final class ForwardConverter
{
    /** @var list<string> */
    private readonly array $vowelKeys;

    /** @var list<string> */
    private readonly array $independentVowelKeys;

    /** @var list<string> */
    private readonly array $consonantKeys;

    /** @var list<string> */
    private readonly array $signKeys;

    /** @var array<string,list<string>> */
    private array $keyChars = [];

    /** @var Closure():object */
    private readonly Closure $defaultOptionsFactory;

    /** @param callable():object $defaultOptionsFactory */
    public function __construct(
        private readonly ForwardScriptConfig $c,
        callable $defaultOptionsFactory,
    ) {
        $this->vowelKeys = $this->sortKeys(array_keys($c->vowelSigns));
        $this->independentVowelKeys = $this->sortKeys(array_keys($c->independentVowels));
        $this->consonantKeys = $this->sortKeys(array_keys($c->consonants));
        $this->signKeys = $this->sortKeys(array_keys($c->signs));
        foreach (array_unique(array_merge($this->vowelKeys, $this->independentVowelKeys, $this->consonantKeys, $this->signKeys)) as $key) {
            $this->keyChars[$key] = Unicode::split($key);
        }

        $this->defaultOptionsFactory = Closure::fromCallable($defaultOptionsFactory);
    }

    public function convert(string $inputText, object $options): string
    {
        if ($inputText === '') {
            return $inputText;
        }

        $text = $inputText;
        if ($this->enumValue($options->omPolicy) === 'useOmSign') {
            $text = $this->protectOmWords($text);
        }

        $chars = Unicode::split($text);
        $out = [];
        $i = 0;
        $length = count($chars);

        while ($i < $length) {
            $ch = $chars[$i];
            $cp = Unicode::ord($ch);

            if (!$options->preserveVedicAccentMarks && isEncodedVedicMark($cp)) {
                ++$i;
                continue;
            }

            if ($ch === "\u{E100}") {
                $out[] = $this->c->omSign;
                ++$i;
                continue;
            }

            if ($this->isLatinChar($ch)) {
                $start = $i;
                ++$i;
                while ($i < $length && $this->isLatinChar($chars[$i])) {
                    ++$i;
                }

                $token = implode('', array_slice($chars, $start, $i - $start));

                $idx = $start - 1;
                while ($idx >= 0 && in_array($chars[$idx], [' ', "\t", "\n", "\r"], true)) {
                    --$idx;
                }

                while ($idx >= 0 && isUnicodeCombiningMark($chars[$idx])) {
                    --$idx;
                }

                $precededByVowel = $idx >= 0 && $this->startsWithVowel($chars, $idx);
                $out[] = $this->convertLatinWord($token, $options, $precededByVowel);
                continue;
            }

            if ($this->enumValue($options->digitPolicy) === 'convertToScript' && array_key_exists($ch, $this->c->digits)) {
                $out[] = $this->c->digits[$ch];
            } elseif ($this->enumValue($options->punctuationPolicy) === 'indicDanda' && ($ch === '.' || $ch === '|')) {
                if ($ch === '|') {
                    if ($i + 1 < $length && $chars[$i + 1] === '|') {
                        $out[] = $this->c->doubleDanda;
                        ++$i;
                    } else {
                        $out[] = $this->c->danda;
                    }
                } else {
                    $dotCount = 1;
                    while ($i + $dotCount < $length && $chars[$i + $dotCount] === '.') {
                        ++$dotCount;
                    }

                    if ($dotCount >= 3) {
                        $out[] = str_repeat('.', $dotCount);
                        $i += $dotCount - 1;
                    } else {
                        $afterIdx = $i + $dotCount;
                        $isBoundary = $afterIdx >= $length || in_array($chars[$afterIdx], [' ', "\n", "\r", "\t"], true);
                        $out[] = $isBoundary
                            ? ($dotCount === 2 ? $this->c->doubleDanda : $this->c->danda)
                            : str_repeat('.', $dotCount);
                        $i += $dotCount - 1;
                    }
                }
            } else {
                $out[] = $ch;
            }

            ++$i;
        }

        $result = implode('', $out);
        if ($options->collapseWhitespace) {
            $result = trim((string) preg_replace('/\s+/u', ' ', $result));
        }

        return $options->embedExactSourceMetadata
            ? embedExactSourceMetadata($result, $inputText)
            : $result;
    }

    /** @param list<string> $keys @return list<string> */
    private function sortKeys(array $keys): array
    {
        usort($keys, static fn(string $a, string $b): int => Unicode::length($b) <=> Unicode::length($a));
        return $keys;
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    private function isLatinChar(string $ch): bool
    {
        $cp = Unicode::ord($ch);
        return ($cp >= 0x41 && $cp <= 0x5A)
            || ($cp >= 0x61 && $cp <= 0x7A)
            || ($cp >= 0x00C0 && $cp <= 0x00FF)
            || ($cp >= 0x0100 && $cp <= 0x024F)
            || ($cp >= 0x0250 && $cp <= 0x02FF)
            || ($cp >= 0x1E00 && $cp <= 0x1EFF)
            || isUnicodeCombiningMark($ch)
            || in_array($cp, [0x27, 0x2018, 0x2019, 0x02BC], true);
    }

    private function isAlphabeticLatinChar(string $ch): bool
    {
        $cp = Unicode::ord($ch);
        return ($cp >= 0x41 && $cp <= 0x5A)
            || ($cp >= 0x61 && $cp <= 0x7A)
            || ($cp >= 0x00C0 && $cp <= 0x00FF)
            || ($cp >= 0x0100 && $cp <= 0x024F)
            || ($cp >= 0x0250 && $cp <= 0x02FF)
            || ($cp >= 0x1E00 && $cp <= 0x1EFF);
    }

    private function convertLatinWord(string $word, object $options, bool $precededByVowel): string
    {
        $text = $this->normalizeAndLowercase($word, $options);
        $chars = Unicode::split($text);
        $out = [];
        $i = 0;
        $pendingConsonant = false;
        $afterVowel = false;
        $deferredAccents = [];

        while ($i < count($chars)) {
            $ch = $chars[$i];

            if ($pendingConsonant) {
                $vowel = $this->matchContextualVowel($chars, $i, $options, true);
                if ($vowel !== null) {
                    if ($vowel !== 'a') {
                        $out[] = $this->c->vowelSigns[$vowel];
                    }

                    $afterVowel = true;
                    if (!$this->nextIsVisargaOrAnusvara($chars, $i + Unicode::length($vowel))) {
                        if ($deferredAccents !== []) {
                            array_push($out, ...$deferredAccents);
                            $deferredAccents = [];
                        }
                    }

                    $i += Unicode::length($vowel);
                    $pendingConsonant = false;
                    continue;
                }

                $sign = $this->matchKey($chars, $i, $this->signKeys);
                if ($sign !== null) {
                    if (in_array($sign, ["'", '‘', '’', 'ʼ'], true)) {
                        if (!$this->isAvagrahaContext($chars, $i, $precededByVowel)) {
                            $out[] = $sign;
                            $i += Unicode::length($sign);
                            $pendingConsonant = false;
                            $afterVowel = false;
                            continue;
                        }

                        if ($deferredAccents !== []) {
                            array_push($out, ...$deferredAccents);
                            $deferredAccents = [];
                        }
                    }

                    if ($this->isVedicAccent($sign)) {
                        $deferredAccents[] = $this->getScriptSign($sign, $options);
                        $i += Unicode::length($sign);
                        continue;
                    }

                    if ($this->isDependentNasalSign($sign)) {
                        $extra = $this->followingCombiningMarks($chars, $i + Unicode::length($sign));
                        if ($extra !== '') {
                            $accentMarks = $this->vedicAccentMarksToScript($extra, $options);
                            if ($accentMarks !== null) {
                                $out[] = $this->getScriptSign($sign, $options);
                                if ($deferredAccents !== []) {
                                    array_push($out, ...$deferredAccents);
                                    $deferredAccents = [];
                                }

                                $out[] = $accentMarks;
                                $i += Unicode::length($sign) + Unicode::length($extra);
                                $pendingConsonant = false;
                                $afterVowel = false;
                                continue;
                            }

                            $out[] = $this->getScriptSign($sign, $options);
                            if ($deferredAccents !== []) {
                                array_push($out, ...$deferredAccents);
                                $deferredAccents = [];
                            }

                            $out[] = $extra;
                            $i += Unicode::length($sign) + Unicode::length($extra);
                            $pendingConsonant = false;
                            $afterVowel = false;
                            continue;
                        }
                    }

                    $out[] = $this->getScriptSign($sign, $options);
                    if ($sign === 'ḥ' || $this->isDependentNasalSign($sign)) {
                        if ($deferredAccents !== []) {
                            array_push($out, ...$deferredAccents);
                            $deferredAccents = [];
                        }
                    }

                    $i += Unicode::length($sign);
                    $pendingConsonant = false;
                    $afterVowel = false;
                    continue;
                }

                $nextConsonant = $this->matchContextualConsonant($chars, $i, $options, true);
                if ($nextConsonant !== null) {
                    $out[] = $this->c->virama;
                    if ($deferredAccents !== []) {
                        array_push($out, ...$deferredAccents);
                        $deferredAccents = [];
                    }

                    $pendingConsonant = false;
                    $afterVowel = false;
                    continue;
                }

                if (isUnicodeCombiningMark($ch)) {
                    if ($options->preserveVedicAccentMarks || !isEncodedVedicMark($ch)) {
                        $out[] = $this->handleUnknownMark($ch, $options);
                    }

                    ++$i;
                    continue;
                }

                $out[] = $this->c->virama;
                $pendingConsonant = false;
                $afterVowel = false;
                continue;
            }

            $sign = $this->matchKey($chars, $i, $this->signKeys);
            if ($sign !== null) {
                if (in_array($sign, ["'", '‘', '’', 'ʼ'], true)) {
                    if (!$this->isAvagrahaContext($chars, $i, $precededByVowel)) {
                        $out[] = $sign;
                        $i += Unicode::length($sign);
                        $afterVowel = false;
                        continue;
                    }

                    if ($deferredAccents !== []) {
                        array_push($out, ...$deferredAccents);
                        $deferredAccents = [];
                    }
                }

                if ($this->isVedicAccent($sign)) {
                    $deferredAccents[] = $this->getScriptSign($sign, $options);
                    $i += Unicode::length($sign);
                    continue;
                }

                if ($sign === 'ḥ' && !$afterVowel) {
                    $out[] = $this->c->consonants['h'];
                    $i += Unicode::length($sign);
                    $pendingConsonant = true;
                    $afterVowel = false;
                    continue;
                }

                if ($this->isDependentNasalSign($sign)) {
                    $extra = $this->followingCombiningMarks($chars, $i + Unicode::length($sign));
                    if ($extra !== '') {
                        $accentMarks = $this->vedicAccentMarksToScript($extra, $options);
                        if ($accentMarks !== null) {
                            $out[] = $this->getScriptSign($sign, $options);
                            if ($deferredAccents !== []) {
                                array_push($out, ...$deferredAccents);
                                $deferredAccents = [];
                            }

                            $out[] = $accentMarks;
                            $i += Unicode::length($sign) + Unicode::length($extra);
                            $afterVowel = false;
                            continue;
                        }

                        if (!$afterVowel) {
                            $out[] = $this->c->dottedCircle;
                        }

                        $out[] = $this->getScriptSign($sign, $options);
                        $out[] = $extra;
                        $i += Unicode::length($sign) + Unicode::length($extra);
                        $afterVowel = false;
                        continue;
                    }

                    if (!$afterVowel) {
                        $out[] = $this->c->dottedCircle;
                        $out[] = $this->getScriptSign($sign, $options);
                        $i += Unicode::length($sign);
                        $afterVowel = false;
                        continue;
                    }
                }

                $out[] = $this->getScriptSign($sign, $options);
                if ($sign === 'ḥ' || $this->isDependentNasalSign($sign)) {
                    if ($deferredAccents !== []) {
                        array_push($out, ...$deferredAccents);
                        $deferredAccents = [];
                    }

                    $afterVowel = false;
                }

                $i += Unicode::length($sign);
                continue;
            }

            $consonant = $this->matchContextualConsonant($chars, $i, $options, false);
            if ($consonant !== null) {
                if ($deferredAccents !== []) {
                    array_push($out, ...$deferredAccents);
                    $deferredAccents = [];
                }

                $out[] = $this->c->consonants[$consonant];
                $i += Unicode::length($consonant);
                $keepsInherentA = in_array($consonant, ['ṛ', 'ṛh'], true)
                    && $this->enumValue($options->profile) !== 'strictIast'
                    && !$this->startsWithVowel($chars, $i);
                $pendingConsonant = !$keepsInherentA;
                $afterVowel = $keepsInherentA;
                continue;
            }

            $vowel = $this->matchContextualVowel($chars, $i, $options, false);
            if ($vowel !== null) {
                $out[] = $this->c->independentVowels[$vowel];
                $afterVowel = true;
                if (!$this->nextIsVisargaOrAnusvara($chars, $i + Unicode::length($vowel))) {
                    if ($deferredAccents !== []) {
                        array_push($out, ...$deferredAccents);
                        $deferredAccents = [];
                    }
                }

                $i += Unicode::length($vowel);
                continue;
            }

            if (isUnicodeCombiningMark($ch)) {
                if ($options->preserveVedicAccentMarks || !isEncodedVedicMark($ch)) {
                    $out[] = $this->handleUnknownMark($ch, $options);
                }

                ++$i;
                continue;
            }

            $out[] = $this->handleUnknownLatin($ch, $options);
            ++$i;
            $afterVowel = false;
        }

        if ($pendingConsonant) {
            $out[] = $this->c->virama;
        }

        if ($deferredAccents !== []) {
            array_push($out, ...$deferredAccents);
        }

        return implode('', $out);
    }

    /** @param list<string> $chars */
    private function nextIsVisargaOrAnusvara(array $chars, int $start): bool
    {
        $idx = $start;
        while ($idx < count($chars)) {
            $sign = $this->matchKey($chars, $idx, $this->signKeys);
            if ($sign !== null && $this->isVedicAccent($sign)) {
                $idx += Unicode::length($sign);
            } else {
                break;
            }
        }

        if ($idx < count($chars)) {
            $nextSign = $this->matchKey($chars, $idx, $this->signKeys);
            return in_array($nextSign, ['ḥ', 'ṃ', 'm̐', "\u{0310}", '̐'], true);
        }

        return false;
    }

    /** @param list<string> $chars */
    private function matchContextualVowel(array $chars, int $i, object $options, bool $pendingConsonant): ?string
    {
        if ($i >= count($chars)) {
            return null;
        }

        $profile = $this->enumValue($options->profile);

        $lMatch = $this->matchLVariant($chars, $i);
        if ($lMatch !== null) {
            if ($profile === 'strictIast') {
                return $lMatch;
            }

            if ($this->startsWithVowel($chars, $i + Unicode::length($lMatch))) {
                return null;
            }

            $policy = $this->enumValue($options->ambiguousLPolicy);
            if ($policy === 'preferVocalic') {
                return $lMatch;
            }

            if ($policy === 'preferConsonant') {
                return null;
            }

            if (!$this->startsWithVowel($chars, $i + Unicode::length($lMatch))) {
                return $lMatch;
            }

            return null;
        }

        $rMatch = $this->matchRVariant($chars, $i);
        if ($rMatch !== null) {
            if ($profile === 'strictIast') {
                return $rMatch;
            }

            if ($pendingConsonant) {
                return $rMatch;
            }

            if (!$this->startsWithVowel($chars, $i + Unicode::length($rMatch)) && !$this->previousStartsWithVowel($chars, $i)) {
                return $rMatch;
            }

            return null;
        }

        $keys = $pendingConsonant ? $this->vowelKeys : $this->independentVowelKeys;
        $match = $this->matchKey($chars, $i, $keys);
        if ($match !== null
            && $profile === 'strictIast'
            && $this->enumValue($options->unknownLatinPolicy) === 'throwError'
            && !isset($this->c->strictIastVowels[$match])) {
            return null;
        }

        return $match;
    }

    /** @param list<string> $chars */
    private function matchContextualConsonant(array $chars, int $i, object $options, bool $pendingConsonant): ?string
    {
        if ($i >= count($chars)) {
            return null;
        }

        $match = $this->matchKey($chars, $i, $this->consonantKeys);
        if ($match === null) {
            return null;
        }

        $profile = $this->enumValue($options->profile);
        if ($profile === 'strictIast' && !isset($this->c->strictIastConsonants[$match])) {
            return null;
        }

        if ($match === 'ṛ' || $match === 'ṛh') {
            if ($profile === 'strictIast') {
                return null;
            }

            if (!$this->startsWithVowel($chars, $i + Unicode::length($match)) && !$this->previousStartsWithVowel($chars, $i)) {
                return null;
            }
        }

        if ($match === 'ḷ') {
            if ($profile === 'strictIast') {
                return null;
            }

            if ($this->startsWithVowel($chars, $i + 1)) {
                return 'ḷ';
            }

            $policy = $this->enumValue($options->ambiguousLPolicy);
            if ($policy === 'preferConsonant') {
                return 'ḷ';
            }

            if ($policy === 'context' && $this->startsWithVowel($chars, $i + 1)) {
                return 'ḷ';
            }

            return null;
        }

        if ($match === 'x' && !$options->acceptPlainXAsKha) {
            return null;
        }

        if ($match === 'w' && !$options->acceptWAsVa) {
            return null;
        }

        if (($match === 'sh' || $match === 'zh') && !$options->acceptPlainSh) {
            return null;
        }

        return $match;
    }

    /** @param list<string> $chars */
    private function matchLVariant(array $chars, int $i): ?string
    {
        if ($this->matchesAt($chars, $i, Unicode::split('ḹ'))) {
            return 'ḹ';
        }

        if ($this->matchesAt($chars, $i, Unicode::split('ḷ'))) {
            return 'ḷ';
        }

        return null;
    }

    /** @param list<string> $chars */
    private function matchRVariant(array $chars, int $i): ?string
    {
        if ($this->matchesAt($chars, $i, Unicode::split('ṝ'))) {
            return 'ṝ';
        }

        if ($this->matchesAt($chars, $i, Unicode::split('ṛ'))) {
            return 'ṛ';
        }

        return null;
    }

    /** @param list<string> $chars */
    private function startsWithVowel(array $chars, int $i): bool
    {
        if ($i < 0 || $i >= count($chars)) {
            return false;
        }

        if ($this->matchKey($chars, $i, $this->vowelKeys) !== null || $this->matchKey($chars, $i, $this->independentVowelKeys) !== null) {
            return true;
        }

        $slice = implode('', array_slice($chars, $i, 12));
        $factory = $this->defaultOptionsFactory;
        $folded = $this->normalizeAndLowercase($slice, $factory());
        $foldedChars = Unicode::split($folded);
        if ($this->matchKey($foldedChars, 0, $this->vowelKeys) !== null) {
            return true;
        }

        return $this->matchKey($foldedChars, 0, $this->independentVowelKeys) !== null;
    }

    /** @param list<string> $chars */
    private function isAvagrahaContext(array $chars, int $i, bool $precededByVowel): bool
    {
        if ($i >= count($chars) - 1 || !$this->isAlphabeticLatinChar($chars[$i + 1])) {
            return false;
        }

        if ($i === 0) {
            return $precededByVowel;
        }

        $prev = $i - 1;
        while ($prev >= 0 && isUnicodeCombiningMark($chars[$prev])) {
            --$prev;
        }

        return $prev >= 0 && $this->startsWithVowel($chars, $prev);
    }

    /** @param list<string> $chars */
    private function previousStartsWithVowel(array $chars, int $i): bool
    {
        $prev = $i - 1;
        while ($prev >= 0 && isUnicodeCombiningMark($chars[$prev])) {
            --$prev;
        }

        return $prev >= 0 && $this->startsWithVowel($chars, $prev);
    }

    /** @param list<string> $chars @param list<string> $keys */
    private function matchKey(array $chars, int $i, array $keys): ?string
    {
        foreach ($keys as $key) {
            if ($this->matchesAt($chars, $i, $this->keyChars[$key])) {
                return $key;
            }
        }

        return null;
    }

    /** @param list<string> $chars @param list<string> $keyChars */
    private function matchesAt(array $chars, int $index, array $keyChars): bool
    {
        if ($index + count($keyChars) > count($chars)) {
            return false;
        }

        foreach ($keyChars as $offset => $ch) {
            if ($chars[$index + $offset] !== $ch) {
                return false;
            }
        }

        return true;
    }

    private function normalizeAndLowercase(string $word, object $options): string
    {
        $nfd = normalizeUnicode($word, UnicodeNormalizationForm::NFD);
        $chars = Unicode::split($nfd);
        $out = [];
        $i = 0;
        while ($i < count($chars)) {
            $base = $chars[$i++];
            $marks = [];
            while ($i < count($chars) && isUnicodeCombiningMark($chars[$i])) {
                $marks[] = Unicode::ord($chars[$i]);
                ++$i;
            }

            $out[] = $this->foldMarkedBase($base, $marks, true);
        }

        $s = Unicode::lower(implode('', $out));
        if ($options->acceptAsciiLongVowels) {
            return str_replace(['aa', 'ii', 'uu', 'rr', 'll'], ['ā', 'ī', 'ū', 'ṝ', 'ḹ'], $s);
        }

        return $s;
    }

    /** @param list<int> $marks */
    private function foldMarkedBase(string $base, array $marks, bool $allowCompatibilityFolding): string
    {
        $foldedBase = $allowCompatibilityFolding ? $this->foldPrecomposed($base) : $base;
        $lower = Unicode::lower($foldedBase);
        if ($marks === []) {
            return $foldedBase;
        }

        $hasDotBelow = in_array(0x0323, $marks, true);
        $hasRingBelow = in_array(0x0325, $marks, true);
        $hasDotAbove = in_array(0x0307, $marks, true);
        $hasAcute = in_array(0x0301, $marks, true);
        $hasNasal = in_array(0x0303, $marks, true) || in_array(0x0310, $marks, true);
        $hasMacron = in_array(0x0304, $marks, true);
        $hasLineBelow = in_array(0x0331, $marks, true) || in_array(0x035F, $marks, true);
        $hasBreveBelow = in_array(0x032E, $marks, true);
        $hasCaron = in_array(0x030C, $marks, true);

        $out = null;
        $consumed = [];
        $take = static function (string $token, array $codes) use (&$out, &$consumed): void {
            $out = $token;
            foreach ($codes as $code) {
                $consumed[$code] = true;
            }
        };
        $lineBelow = array_values(array_filter([0x0331, 0x035F], static fn(int $cp): bool => in_array($cp, $marks, true)));

        if ($lower === 'r' && ($hasDotBelow || $hasRingBelow)) {
            $codes = [];
            if ($hasDotBelow) { $codes[] = 0x0323; }

            if ($hasRingBelow) { $codes[] = 0x0325; }

            if ($hasMacron) { $codes[] = 0x0304; }

            $take($hasMacron ? 'ṝ' : 'ṛ', $codes);
        } elseif ($lower === 'r' && $hasDotAbove) {
            $take('ṙ', [0x0307]);
        } elseif ($lower === 'r' && $hasLineBelow) {
            $take('ṟ', $lineBelow);
        } elseif ($lower === 'l' && ($hasDotBelow || $hasRingBelow)) {
            $codes = [];
            if ($hasDotBelow) { $codes[] = 0x0323; }

            if ($hasRingBelow) { $codes[] = 0x0325; }

            if ($hasMacron) { $codes[] = 0x0304; }

            $take($hasMacron ? 'ḹ' : 'ḷ', $codes);
        } elseif ($lower === 'l' && ($hasLineBelow || in_array(0x0324, $marks, true))) {
            $codes = $lineBelow;
            if (in_array(0x0324, $marks, true)) { $codes[] = 0x0324; }

            $take('ḻ', $codes);
        } elseif ($lower === 'h' && $hasBreveBelow) {
            $take('ḫ', [0x032E]);
        } elseif ($lower === 'h' && $hasDotBelow) {
            $take('ḥ', [0x0323]);
        } elseif ($lower === 'h' && $hasLineBelow) {
            $take('ẖ', $lineBelow);
        } elseif ($lower === 's' && $hasDotBelow) {
            $take('ṣ', [0x0323]);
        } elseif ($lower === 's' && $hasAcute) {
            $take('ś', [0x0301]);
        } elseif ($lower === 's' && $hasDotAbove) {
            $take('ṡ', [0x0307]);
        } elseif ($lower === 's' && $hasLineBelow) {
            $take('s̱', $lineBelow);
        } elseif ($lower === 't' && $hasDotBelow) {
            $take('ṭ', [0x0323]);
        } elseif ($lower === 't' && $hasLineBelow) {
            $take('ṯ', $lineBelow);
        } elseif ($lower === 'd' && $hasDotBelow) {
            $take('ḍ', [0x0323]);
        } elseif ($lower === 'd' && $hasLineBelow) {
            $take('ḏ', $lineBelow);
        } elseif ($lower === 'n' && $hasDotBelow) {
            $take('ṇ', [0x0323]);
        } elseif ($lower === 'n' && $hasDotAbove) {
            $take('ṅ', [0x0307]);
        } elseif ($lower === 'n' && $hasNasal) {
            $codes = [];
            if (in_array(0x0303, $marks, true)) { $codes[] = 0x0303; }

            if (in_array(0x0310, $marks, true)) { $codes[] = 0x0310; }

            $take('ñ', $codes);
        } elseif ($lower === 'n' && $hasLineBelow) {
            $take('ṉ', $lineBelow);
        } elseif ($lower === 'z' && $hasDotBelow) {
            $take('ẓ', [0x0323]);
        } elseif ($lower === 'z' && $hasCaron) {
            $take('ž', [0x030C]);
        } elseif ($lower === 'z' && $hasDotAbove) {
            $take('ż', [0x0307]);
        } elseif ($lower === 'z' && $hasLineBelow) {
            $take('ẕ', $lineBelow);
        } elseif ($lower === 'k' && $hasDotBelow) {
            $take('ḳ', [0x0323]);
        } elseif ($lower === 'k' && $hasLineBelow) {
            $take('ḵ', $lineBelow);
        } elseif ($lower === 'g' && $hasCaron) {
            $take('ǧ', [0x030C]);
        } elseif ($lower === 'g' && $hasDotAbove) {
            $take('ġ', [0x0307]);
        } elseif ($lower === 'g' && $hasLineBelow) {
            $take('g̱', $lineBelow);
        } elseif ($lower === 'm' && in_array(0x0310, $marks, true)) {
            $take('m̐', [0x0310]);
        } elseif ($lower === 'm' && ($hasDotBelow || $hasDotAbove)) {
            $codes = [];
            if ($hasDotBelow) { $codes[] = 0x0323; }

            if ($hasDotAbove) { $codes[] = 0x0307; }

            $take('ṃ', $codes);
        } elseif ($lower === 'y' && $hasDotAbove) {
            $take('ẏ', [0x0307]);
        } elseif ($hasMacron) {
            $token = ['a' => 'ā', 'i' => 'ī', 'u' => 'ū', 'e' => 'ē', 'o' => 'ō'][$lower] ?? null;
            if ($token !== null) { $take($token, [0x0304]); }
        } elseif (in_array(0x0306, $marks, true)) {
            $token = ['a' => 'ă', 'e' => 'ĕ', 'o' => 'ŏ'][$lower] ?? null;
            if ($token !== null) { $take($token, [0x0306]); }
        } elseif (in_array(0x0302, $marks, true)) {
            $token = ['e' => 'ê', 'o' => 'ô'][$lower] ?? null;
            if ($token !== null) { $take($token, [0x0302]); }
        }

        $outMarks = '';
        foreach ($marks as $cp) {
            if (!isset($consumed[$cp])) {
                $outMarks .= Unicode::chr($cp);
            }
        }

        return ($out ?? $foldedBase) . $outMarks;
    }

    private function foldPrecomposed(string $ch): string
    {
        static $mapping = [
            'á' => "a\u{0301}", 'à' => "a\u{0300}", 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'é' => "e\u{0301}", 'è' => "e\u{0300}", 'ë' => 'e',
            'í' => "i\u{0301}", 'ì' => "i\u{0300}", 'î' => 'i', 'ï' => 'i',
            'ó' => "o\u{0301}", 'ò' => "o\u{0300}", 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ú' => "u\u{0301}", 'ù' => "u\u{0300}", 'û' => 'u', 'ü' => 'u',
            'ç' => 's', 'Ç' => 'S', 'ł' => 'l', 'Ł' => 'L', 'ß' => 'ss',
            'þ' => 'th', 'Þ' => 'Th', 'ð' => 'd', 'Ð' => 'D',
            'Æ' => 'ae', 'æ' => 'ae', 'Œ' => 'oe', 'œ' => 'oe', 'ź' => 'z', 'Ź' => 'Z',
        ];
        return $mapping[$ch] ?? $ch;
    }

    private function getScriptSign(string $sign, object $options): string
    {
        if (!$options->preserveVedicAccentMarks && $this->isVedicAccent($sign)) {
            return '';
        }

        return $this->c->signs[$sign] ?? $sign;
    }

    private function isVedicAccent(string $sign): bool
    {
        return in_array($sign, ["\u{0301}", "\u{0300}", "\u{030D}", "\u{030E}", "\u{0302}", "\u{0320}"], true);
    }

    private function isDependentNasalSign(?string $sign): bool
    {
        return $sign !== null && in_array($sign, ['ṃ', 'ṁ', 'm̐', "\u{0310}", '̐', '̃'], true);
    }

    private function vedicAccentMarksToScript(string $marks, object $options): ?string
    {
        $out = '';
        foreach (Unicode::split($marks) as $mark) {
            if (!$this->isVedicAccent($mark)) {
                return null;
            }

            $out .= $this->getScriptSign($mark, $options);
        }

        return $out;
    }

    /** @param list<string> $chars */
    private function followingCombiningMarks(array $chars, int $start): string
    {
        $out = '';
        $i = $start;
        while ($i < count($chars) && isUnicodeCombiningMark($chars[$i])) {
            $out .= $chars[$i];
            ++$i;
        }

        return $out;
    }

    private function handleUnknownMark(string $ch, object $options): string
    {
        return match ($this->enumValue($options->unknownLatinPolicy)) {
            'passThrough' => $ch,
            'bracket' => '[' . $ch . ']',
            default => throw new InvalidArgumentException(sprintf('Unknown combining mark: U+%04X', Unicode::ord($ch))),
        };
    }

    private function handleUnknownLatin(string $ch, object $options): string
    {
        return match ($this->enumValue($options->unknownLatinPolicy)) {
            'passThrough' => $ch,
            'bracket' => '[' . $ch . ']',
            default => throw new InvalidArgumentException('Unknown Latin token: ' . $ch),
        };
    }

    private function protectOmWords(string $text): string
    {
        $pattern = '/(?<![A-Za-z\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}\x{0300}-\x{036F}])(oṃ|oṁ|oṁ|aum)(?![A-Za-z\x{00C0}-\x{024F}\x{1E00}-\x{1EFF}\x{0300}-\x{036F}])/iu';
        return (string) preg_replace($pattern, "\u{E100}", $text);
    }
}
