<?php

declare(strict_types=1);

namespace IndicScriptConverter;

use InvalidArgumentException;
use OutOfRangeException;

require_once __DIR__ . '/UnicodeData.php';

/**
 * Dependency-free Unicode primitives required by the transliteration port.
 *
 * PHP strings are byte strings, so this helper provides deterministic UTF-8
 * code-point iteration, simple Unicode case mapping, and canonical NFC/NFD.
 * The normalization and mark-category tables are generated from the official
 * Unicode Character Database 17.0.0 and are bundled so PHP intl/mbstring are
 * not required at runtime.
 */
final class Unicode
{
    private const int S_BASE = 0xAC00;

    private const int L_BASE = 0x1100;

    private const int V_BASE = 0x1161;

    private const int T_BASE = 0x11A7;

    private const int L_COUNT = 19;

    private const int V_COUNT = 21;

    private const int T_COUNT = 28;

    private const N_COUNT = self::V_COUNT * self::T_COUNT;

    private const S_COUNT = self::L_COUNT * self::N_COUNT;

    private function __construct() {}

    /** @return list<int> */
    public static function codePoints(string $text): array
    {
        $result = [];
        $length = strlen($text);
        $i = 0;
        while ($i < $length) {
            $b1 = ord($text[$i]);
            if ($b1 < 0x80) {
                $result[] = $b1;
                ++$i;
                continue;
            }

            if (($b1 & 0xE0) === 0xC0 && $i + 1 < $length) {
                $b2 = ord($text[$i + 1]);
                if (($b2 & 0xC0) === 0x80) {
                    $cp = (($b1 & 0x1F) << 6) | ($b2 & 0x3F);
                    if ($cp >= 0x80) {
                        $result[] = $cp;
                        $i += 2;
                        continue;
                    }
                }
            } elseif (($b1 & 0xF0) === 0xE0 && $i + 2 < $length) {
                $b2 = ord($text[$i + 1]);
                $b3 = ord($text[$i + 2]);
                if (($b2 & 0xC0) === 0x80 && ($b3 & 0xC0) === 0x80) {
                    $cp = (($b1 & 0x0F) << 12) | (($b2 & 0x3F) << 6) | ($b3 & 0x3F);
                    // Deliberately allow surrogate code points (WTF-8) so the
                    // exact metadata layer can preserve isolated UTF-16 units.
                    if ($cp >= 0x800) {
                        $result[] = $cp;
                        $i += 3;
                        continue;
                    }
                }
            } elseif (($b1 & 0xF8) === 0xF0 && $i + 3 < $length) {
                $b2 = ord($text[$i + 1]);
                $b3 = ord($text[$i + 2]);
                $b4 = ord($text[$i + 3]);
                if (($b2 & 0xC0) === 0x80 && ($b3 & 0xC0) === 0x80 && ($b4 & 0xC0) === 0x80) {
                    $cp = (($b1 & 0x07) << 18) | (($b2 & 0x3F) << 12) | (($b3 & 0x3F) << 6) | ($b4 & 0x3F);
                    if ($cp >= 0x10000 && $cp <= 0x10FFFF) {
                        $result[] = $cp;
                        $i += 4;
                        continue;
                    }
                }
            }

            throw new InvalidArgumentException(sprintf('Invalid UTF-8 byte sequence at byte offset %d.', $i));
        }

        return $result;
    }

    /** @param iterable<int> $codePoints */
    public static function fromCodePoints(iterable $codePoints): string
    {
        $out = '';
        foreach ($codePoints as $cp) {
            $out .= self::chr($cp);
        }

        return $out;
    }

    public static function chr(int $cp): string
    {
        if ($cp < 0 || $cp > 0x10FFFF) {
            throw new OutOfRangeException(sprintf('Invalid Unicode code point U+%X.', $cp));
        }

        if ($cp <= 0x7F) {
            return chr($cp);
        }

        if ($cp <= 0x7FF) {
            return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
        }

        if ($cp <= 0xFFFF) {
            return chr(0xE0 | ($cp >> 12))
                . chr(0x80 | (($cp >> 6) & 0x3F))
                . chr(0x80 | ($cp & 0x3F));
        }

        return chr(0xF0 | ($cp >> 18))
            . chr(0x80 | (($cp >> 12) & 0x3F))
            . chr(0x80 | (($cp >> 6) & 0x3F))
            . chr(0x80 | ($cp & 0x3F));
    }

    public static function ord(string $character): int
    {
        $points = self::codePoints($character);
        if (count($points) !== 1) {
            throw new InvalidArgumentException('Expected exactly one Unicode code point.');
        }

        return $points[0];
    }

    /** @return list<string> */
    public static function split(string $text): array
    {
        return array_map(self::chr(...), self::codePoints($text));
    }

    public static function length(string $text): int
    {
        return count(self::codePoints($text));
    }

    public static function slice(string $text, int $start, ?int $length = null): string
    {
        $points = self::codePoints($text);
        $slice = $length === null ? array_slice($points, $start) : array_slice($points, $start, $length);
        return self::fromCodePoints($slice);
    }

    public static function lower(string $text): string
    {
        $out = '';
        foreach (self::codePoints($text) as $cp) {
            $out .= UnicodeData::LOWER[$cp] ?? self::chr($cp);
        }

        return $out;
    }

    public static function upper(string $text): string
    {
        $out = '';
        foreach (self::codePoints($text) as $cp) {
            $out .= UnicodeData::UPPER[$cp] ?? self::chr($cp);
        }

        return $out;
    }

    public static function isCombiningMark(int|string $value): bool
    {
        $cp = is_int($value) ? $value : self::ord($value);
        foreach (UnicodeData::MARK_RANGES as [$start, $end]) {
            if ($cp < $start) {
                return false;
            }

            if ($cp <= $end) {
                return true;
            }
        }

        return false;
    }

    public static function combiningClass(int $cp): int
    {
        return UnicodeData::COMBINING_CLASS[$cp] ?? 0;
    }

    public static function normalize(string $text, UnicodeNormalizationForm $form): string
    {
        return match ($form) {
            UnicodeNormalizationForm::PRESERVE => $text,
            UnicodeNormalizationForm::NFD => self::nfd($text),
            UnicodeNormalizationForm::NFC => self::nfc($text),
        };
    }

    public static function nfd(string $text): string
    {
        $decomposed = [];
        foreach (self::codePoints($text) as $cp) {
            self::decomposeCanonical($cp, $decomposed);
        }

        self::canonicalOrder($decomposed);
        return self::fromCodePoints($decomposed);
    }

    public static function nfc(string $text): string
    {
        $points = self::codePoints(self::nfd($text));
        if ($points === []) {
            return '';
        }

        $result = [$points[0]];
        $starterPos = 0;
        $starter = $points[0];
        $lastClass = self::combiningClass($starter);
        if ($lastClass !== 0) {
            $starterPos = -1;
        }

        $count = count($points);
        for ($i = 1; $i < $count; ++$i) {
            $cp = $points[$i];
            $class = self::combiningClass($cp);
            $composite = $starterPos >= 0 ? self::composePair($starter, $cp) : null;

            if ($composite !== null && ($lastClass < $class || $lastClass === 0)) {
                $result[$starterPos] = $composite;
                $starter = $composite;
            } else {
                if ($class === 0) {
                    $starterPos = count($result);
                    $starter = $cp;
                }

                $result[] = $cp;
                $lastClass = $class;
            }
        }

        return self::fromCodePoints($result);
    }

    /** Return exact UTF-16LE bytes for a PHP Unicode/WTF-8 string. */
    public static function toUtf16Le(string $text): string
    {
        $bytes = '';
        foreach (self::codePoints($text) as $cp) {
            if ($cp <= 0xFFFF) {
                $bytes .= chr($cp & 0xFF) . chr(($cp >> 8) & 0xFF);
                continue;
            }

            $value = $cp - 0x10000;
            $high = 0xD800 | ($value >> 10);
            $low = 0xDC00 | ($value & 0x3FF);
            $bytes .= chr($high & 0xFF) . chr(($high >> 8) & 0xFF);
            $bytes .= chr($low & 0xFF) . chr(($low >> 8) & 0xFF);
        }

        return $bytes;
    }

    public static function fromUtf16Le(string $bytes): string
    {
        $length = strlen($bytes);
        if (($length & 1) !== 0) {
            throw new InvalidArgumentException('Invalid UTF-16LE source payload length.');
        }

        $units = [];
        for ($i = 0; $i < $length; $i += 2) {
            $units[] = ord($bytes[$i]) | (ord($bytes[$i + 1]) << 8);
        }

        $points = [];
        for ($i = 0, $count = count($units); $i < $count; ++$i) {
            $unit = $units[$i];
            if ($unit >= 0xD800 && $unit <= 0xDBFF && $i + 1 < $count) {
                $low = $units[$i + 1];
                if ($low >= 0xDC00 && $low <= 0xDFFF) {
                    $points[] = 0x10000 + (($unit - 0xD800) << 10) + ($low - 0xDC00);
                    ++$i;
                    continue;
                }
            }

            $points[] = $unit;
        }

        return self::fromCodePoints($points);
    }

    /** @param list<int> $out */
    private static function decomposeCanonical(int $cp, array &$out): void
    {
        $sIndex = $cp - self::S_BASE;
        if ($sIndex >= 0 && $sIndex < self::S_COUNT) {
            $l = self::L_BASE + intdiv($sIndex, self::N_COUNT);
            $v = self::V_BASE + intdiv($sIndex % self::N_COUNT, self::T_COUNT);
            $t = self::T_BASE + ($sIndex % self::T_COUNT);
            $out[] = $l;
            $out[] = $v;
            if ($t !== self::T_BASE) {
                $out[] = $t;
            }

            return;
        }

        $mapping = UnicodeData::CANONICAL_DECOMPOSITION[$cp] ?? null;
        if ($mapping === null) {
            $out[] = $cp;
            return;
        }

        foreach ($mapping as $part) {
            self::decomposeCanonical($part, $out);
        }
    }

    /** @param list<int> $points */
    private static function canonicalOrder(array &$points): void
    {
        for ($i = 1, $count = count($points); $i < $count; ++$i) {
            $class = self::combiningClass($points[$i]);
            if ($class === 0) {
                continue;
            }

            $j = $i;
            while ($j > 0) {
                $previousClass = self::combiningClass($points[$j - 1]);
                if ($previousClass === 0 || $previousClass <= $class) {
                    break;
                }

                [$points[$j - 1], $points[$j]] = [$points[$j], $points[$j - 1]];
                --$j;
            }
        }
    }

    private static function composePair(int $a, int $b): ?int
    {
        // Hangul algorithmic composition.
        $lIndex = $a - self::L_BASE;
        if ($lIndex >= 0 && $lIndex < self::L_COUNT) {
            $vIndex = $b - self::V_BASE;
            if ($vIndex >= 0 && $vIndex < self::V_COUNT) {
                return self::S_BASE + ($lIndex * self::V_COUNT + $vIndex) * self::T_COUNT;
            }
        }

        $sIndex = $a - self::S_BASE;
        if ($sIndex >= 0 && $sIndex < self::S_COUNT && ($sIndex % self::T_COUNT) === 0) {
            $tIndex = $b - self::T_BASE;
            if ($tIndex > 0 && $tIndex < self::T_COUNT) {
                return $a + $tIndex;
            }
        }

        return UnicodeData::COMPOSITION[sprintf('%X:%X', $a, $b)] ?? null;
    }
}
