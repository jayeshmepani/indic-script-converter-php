<?php

declare(strict_types=1);

namespace IndicScriptConverter;

use InvalidArgumentException;

require_once __DIR__ . '/TransliterationCore.php';

enum IndicScriptUnknownPolicy: string
{
    case PRESERVE = 'preserve';
    case THROW_ERROR = 'throwError';

    public const preserve = self::PRESERVE;

    public const throwError = self::THROW_ERROR;
}

enum IndicScriptDigitPolicy: string
{
    case CONVERT_TO_TARGET = 'convertToTarget';
    case PRESERVE_SOURCE = 'preserveSource';

    public const convertToTarget = self::CONVERT_TO_TARGET;

    public const preserveSource = self::PRESERVE_SOURCE;
}

final readonly class IndicScriptConversionOptions
{
    public function __construct(
        public UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
        public UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
        public IndicScriptUnknownPolicy $unknownPolicy = IndicScriptUnknownPolicy::PRESERVE,
        public IndicScriptDigitPolicy $digitPolicy = IndicScriptDigitPolicy::CONVERT_TO_TARGET,
        public bool $collapseWhitespace = false,
        public bool $embedExactSourceMetadata = false,
    ) {}
}

const DEVA_DIGIT_START = 0x0966;
const DEVA_DIGIT_END = 0x096F;
const GUJR_DIGIT_START = 0x0AE6;
const GUJR_DIGIT_END = 0x0AEF;
const DEVA_SOURCE_METADATA_PREFIX = "\0ISC:D:";
const GUJR_SOURCE_METADATA_PREFIX = "\0ISC:G:";

/**
 * @return array<string,string>
 */
function buildOffsetMap(array $ranges): array
{
    $map = [];
    foreach ($ranges as [$start, $end, $delta]) {
        for ($cp = $start; $cp <= $end; ++$cp) {
            $map[Unicode::chr($cp)] = Unicode::chr($cp + $delta);
        }
    }

    return $map;
}

/**
 * @return array<string,string>
 */
function devaToGujrSingleMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = buildOffsetMap([
        [0x0901, 0x0903, 0x0180],
        [0x0905, 0x090C, 0x0180],
        [0x090F, 0x0910, 0x0180],
        [0x0913, 0x0928, 0x0180],
        [0x092A, 0x0930, 0x0180],
        [0x0932, 0x0933, 0x0180],
        [0x0935, 0x0939, 0x0180],
        [0x093C, 0x0945, 0x0180],
        [0x0947, 0x0949, 0x0180],
        [0x094B, 0x094D, 0x0180],
        [0x0960, 0x0963, 0x0180],
        [0x0966, 0x0971, 0x0180],
    ]);

    foreach ([
        'ॐ' => 'ૐ',
        'ऄ' => 'અ',
        'ऍ' => 'ઍ',
        'ऎ' => 'ઍ',
        'ऑ' => 'ઑ',
        'ऒ' => 'ઑ',
        'ॲ' => 'ઍ',
        'ॳ' => 'ઓએ',
        'ॴ' => 'ઓએ',
        'ॵ' => 'ઑ',
        'ॶ' => 'ઉએ',
        'ॷ' => 'ઊએ',
        'ऺ' => 'ોએ',
        'ऻ' => 'ોએ',
        'ॆ' => 'ૅ',
        'ॊ' => 'ૉ',
        'ॏ' => 'ૉ',
        'ॖ' => 'ુએ',
        'ॗ' => 'ૂએ',
        'ऩ' => 'ન઼',
        'ऱ' => 'ર઼',
        'ऴ' => 'ળ',
        'क़' => 'ક઼',
        'ख़' => 'ખ઼',
        'ग़' => 'ગ઼',
        'ज़' => 'જ઼',
        'ड़' => 'ડ઼',
        'ढ़' => 'ઢ઼',
        'फ़' => 'ફ઼',
        'य़' => 'ય઼',
        'ॸ' => 'ડ઼',
        'ॹ' => 'ૹ',
        'ॺ' => 'ય઼',
        'ॻ' => 'ગ઼',
        'ॼ' => 'જ઼',
        'ॽ' => 'ઽ',
        'ॾ' => 'ડ઼',
        'ॿ' => 'બ઼',
    ] as $source => $target) {
        $map[$source] = $target;
    }

    return $map;
}

/**
 * @return array<string,string>
 */
function gujrToDevaSingleMap(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $map = buildOffsetMap([
        [0x0A81, 0x0A83, -0x0180],
        [0x0A85, 0x0A8C, -0x0180],
        [0x0A8F, 0x0A90, -0x0180],
        [0x0A93, 0x0AA8, -0x0180],
        [0x0AAA, 0x0AB0, -0x0180],
        [0x0AB2, 0x0AB3, -0x0180],
        [0x0AB5, 0x0AB9, -0x0180],
        [0x0ABC, 0x0AC5, -0x0180],
        [0x0AC7, 0x0AC9, -0x0180],
        [0x0ACB, 0x0ACD, -0x0180],
        [0x0AE0, 0x0AE3, -0x0180],
        [0x0AE6, 0x0AF1, -0x0180],
    ]);

    foreach ([
        'ૐ' => 'ॐ',
        'ઍ' => 'ऎ',
        'ઑ' => 'ऒ',
        'ૅ' => 'ॆ',
        'ૉ' => 'ॊ',
        'ૹ' => 'ॹ',
    ] as $source => $target) {
        $map[$source] = $target;
    }

    return $map;
}

/**
 * @return array<string,string>
 */
function devaToGujrSequenceMap(): array
{
    return [
        'ऩ' => 'ન઼',
        'ऱ' => 'ર઼',
        'ऴ' => 'ળ',
        'क़' => 'ક઼',
        'ख़' => 'ખ઼',
        'ग़' => 'ગ઼',
        'ज़' => 'જ઼',
        'ड़' => 'ડ઼',
        'ढ़' => 'ઢ઼',
        'फ़' => 'ફ઼',
        'य़' => 'ય઼',
        'त़' => 'ત઼',
        'द़' => 'દ઼',
        'ह़' => 'હ઼',
        'स़' => 'સ઼',
        'ब़' => 'બ઼',
    ];
}

/**
 * @return array<string,string>
 */
function gujrToDevaSequenceMap(): array
{
    return [
        'ન઼' => 'ऩ',
        'ર઼' => 'ऱ',
        'ક઼' => 'क़',
        'ખ઼' => 'ख़',
        'ગ઼' => 'ग़',
        'જ઼' => 'ज़',
        'ડ઼' => 'ड़',
        'ઢ઼' => 'ढ़',
        'ફ઼' => 'फ़',
        'ય઼' => 'य़',
        'ત઼' => 'त़',
        'દ઼' => 'द़',
        'હ઼' => 'ह़',
        'સ઼' => 'स़',
        'બ઼' => 'ॿ',
    ];
}

/**
 * @return list<array{source:list<int>,target:string}>
 */
function buildScriptSequenceRules(array $mappings): array
{
    $rules = [];
    foreach ($mappings as $source => $target) {
        $rules[] = ['source' => Unicode::codePoints($source), 'target' => $target];
    }

    usort(
        $rules,
        static fn(array $a, array $b): int => count($b['source']) <=> count($a['source']),
    );
    return $rules;
}

/**
 * @param list<int> $codePoints @param list<int> $key
 */
function scriptSequenceMatchesAt(array $codePoints, int $index, array $key): bool
{
    $keyLength = count($key);
    if ($index + $keyLength > count($codePoints)) {
        return false;
    }

    for ($offset = 0; $offset < $keyLength; ++$offset) {
        if ($codePoints[$index + $offset] !== $key[$offset]) {
            return false;
        }
    }

    return true;
}

function handleScriptUnknown(
    string $character,
    IndicScriptConversionOptions $options,
    string $sourceName,
    int $index,
): string {
    return match ($options->unknownPolicy) {
        IndicScriptUnknownPolicy::PRESERVE => $character,
        IndicScriptUnknownPolicy::THROW_ERROR => throw new InvalidArgumentException(
            sprintf(
                'Unmapped %s character U+%04X at code-point offset %d.',
                $sourceName,
                Unicode::ord($character),
                $index,
            ),
        ),
    };
}

function recoverTypedExactSource(string $input, string $expectedPrefix): ?string
{
    $recovered = recoverEmbeddedExactSource($input);
    if ($recovered === null || !str_starts_with($recovered, $expectedPrefix)) {
        return null;
    }

    return substr($recovered, strlen($expectedPrefix));
}

/**
 * @param array<string,string> $singleMap
 * @param list<array{source:list<int>,target:string}> $sequenceRules
 */
function convertCanonicalIndicScript(
    string $input,
    IndicScriptConversionOptions $options,
    string $sourceName,
    array $singleMap,
    array $sequenceRules,
    int $sourceDigitStart,
    int $sourceDigitEnd,
    int $targetDigitStart,
    string $metadataSourcePrefix,
): string {
    $visibleInput = stripExactSourceMetadata($input);
    $normalized = normalizeUnicode($visibleInput, $options->inputNormalization);
    $codePoints = Unicode::codePoints($normalized);
    $output = '';
    $index = 0;
    $length = count($codePoints);

    while ($index < $length) {
        $matched = false;
        foreach ($sequenceRules as $rule) {
            if (!scriptSequenceMatchesAt($codePoints, $index, $rule['source'])) {
                continue;
            }

            $output .= $rule['target'];
            $index += count($rule['source']);
            $matched = true;
            break;
        }

        if ($matched) {
            continue;
        }

        $cp = $codePoints[$index];
        $character = Unicode::chr($cp);
        if ($cp >= $sourceDigitStart && $cp <= $sourceDigitEnd) {
            $output .= match ($options->digitPolicy) {
                IndicScriptDigitPolicy::PRESERVE_SOURCE => $character,
                IndicScriptDigitPolicy::CONVERT_TO_TARGET => Unicode::chr(
                    $targetDigitStart + ($cp - $sourceDigitStart),
                ),
            };
            ++$index;
            continue;
        }

        $output .= $singleMap[$character] ?? handleScriptUnknown(
            $character,
            $options,
            $sourceName,
            $index,
        );
        ++$index;
    }

    $rendered = normalizeUnicode($output, $options->outputNormalization);
    if ($options->collapseWhitespace) {
        $rendered = preg_replace('/\s+/u', ' ', $rendered) ?? $rendered;
        $rendered = trim($rendered);
    }

    return $options->embedExactSourceMetadata
        ? embedExactSourceMetadata($rendered, $metadataSourcePrefix . $visibleInput)
        : $rendered;
}

function toCanonicalDevanagariFromGujarati(
    string $input,
    ?IndicScriptConversionOptions $options = null,
): string {
    $options ??= new IndicScriptConversionOptions;
    static $rules = null;
    $rules ??= buildScriptSequenceRules(gujrToDevaSequenceMap());
    return convertCanonicalIndicScript(
        $input,
        $options,
        'Gujarati',
        gujrToDevaSingleMap(),
        $rules,
        GUJR_DIGIT_START,
        GUJR_DIGIT_END,
        DEVA_DIGIT_START,
        GUJR_SOURCE_METADATA_PREFIX,
    );
}

function to_canonical_devanagari_from_gujarati(string $input, ?IndicScriptConversionOptions $options = null): string
{
    return toCanonicalDevanagariFromGujarati($input, $options);
}

function toDevanagariFromGujarati(
    string $input,
    ?IndicScriptConversionOptions $options = null,
): string {
    return recoverTypedExactSource($input, DEVA_SOURCE_METADATA_PREFIX)
        ?? toCanonicalDevanagariFromGujarati($input, $options);
}

function to_devanagari_from_gujarati(string $input, ?IndicScriptConversionOptions $options = null): string
{
    return toDevanagariFromGujarati($input, $options);
}

function toExactDevanagariFromGujarati(string $input): string
{
    if ($input === '') {
        return '';
    }

    $exact = recoverTypedExactSource($input, DEVA_SOURCE_METADATA_PREFIX);
    if ($exact === null) {
        throw new InvalidArgumentException(
            'No valid embedded exact-source metadata was found. Convert with '
            . 'IndicScriptConversionOptions(embedExactSourceMetadata: true).',
        );
    }

    return $exact;
}

function to_exact_devanagari_from_gujarati(string $input): string
{
    return toExactDevanagariFromGujarati($input);
}

function toCanonicalGujaratiFromDevanagari(
    string $input,
    ?IndicScriptConversionOptions $options = null,
): string {
    $options ??= new IndicScriptConversionOptions;
    static $rules = null;
    $rules ??= buildScriptSequenceRules(devaToGujrSequenceMap());
    return convertCanonicalIndicScript(
        $input,
        $options,
        'Devanagari',
        devaToGujrSingleMap(),
        $rules,
        DEVA_DIGIT_START,
        DEVA_DIGIT_END,
        GUJR_DIGIT_START,
        DEVA_SOURCE_METADATA_PREFIX,
    );
}

function to_canonical_gujarati_from_devanagari(string $input, ?IndicScriptConversionOptions $options = null): string
{
    return toCanonicalGujaratiFromDevanagari($input, $options);
}

function toGujaratiFromDevanagari(
    string $input,
    ?IndicScriptConversionOptions $options = null,
): string {
    return recoverTypedExactSource($input, GUJR_SOURCE_METADATA_PREFIX)
        ?? toCanonicalGujaratiFromDevanagari($input, $options);
}

function to_gujarati_from_devanagari(string $input, ?IndicScriptConversionOptions $options = null): string
{
    return toGujaratiFromDevanagari($input, $options);
}

function toExactGujaratiFromDevanagari(string $input): string
{
    if ($input === '') {
        return '';
    }

    $exact = recoverTypedExactSource($input, GUJR_SOURCE_METADATA_PREFIX);
    if ($exact === null) {
        throw new InvalidArgumentException(
            'No valid embedded exact-source metadata was found. Convert with '
            . 'IndicScriptConversionOptions(embedExactSourceMetadata: true).',
        );
    }

    return $exact;
}

function to_exact_gujarati_from_devanagari(string $input): string
{
    return toExactGujaratiFromDevanagari($input);
}

function hasExactGujaratiScriptSourceMetadata(string $input): bool
{
    return recoverTypedExactSource($input, GUJR_SOURCE_METADATA_PREFIX) !== null;
}

function has_exact_gujarati_source_metadata(string $input): bool
{
    return hasExactGujaratiScriptSourceMetadata($input);
}

function hasExactDevanagariScriptSourceMetadata(string $input): bool
{
    return recoverTypedExactSource($input, DEVA_SOURCE_METADATA_PREFIX) !== null;
}

function has_exact_devanagari_source_metadata(string $input): bool
{
    return hasExactDevanagariScriptSourceMetadata($input);
}

function visibleWithoutExactSourceMetadata(string $input): string
{
    return stripExactSourceMetadata($input);
}

function visible_without_exact_source_metadata(string $input): string
{
    return visibleWithoutExactSourceMetadata($input);
}
