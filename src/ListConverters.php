<?php

declare(strict_types=1);

namespace Lipimala;

/**
 * Bulk converts an array of IAST strings to Devanagari script strings.
 *
 * @param array<string> $items array of IAST strings
 * @param IastToDevanagariOptions|null $options conversion options
 *
 * @return array<string> converted Devanagari strings
 */
function toDevanagariFromIastList(array $items, ?IastToDevanagariOptions $options = null): array
{
    $options ??= new IastToDevanagariOptions;
    return array_map(static fn(string $item): string => toDevanagariFromIast($item, $options), $items);
}

/**
 * Bulk converts an array of IAST strings to Gujarati script strings.
 *
 * @param array<string> $items array of IAST strings
 * @param IastToGujaratiOptions|null $options conversion options
 *
 * @return array<string> converted Gujarati strings
 */
function toGujaratiFromIastList(array $items, ?IastToGujaratiOptions $options = null): array
{
    $options ??= new IastToGujaratiOptions;
    return array_map(static fn(string $item): string => toGujaratiFromIast($item, $options), $items);
}

/**
 * Bulk converts an array of IAST strings to Plain English strings.
 *
 * @param array<string> $items array of IAST strings
 * @param IastPlainEnglishOptions|null $options conversion options
 *
 * @return array<string> converted Plain English strings
 */
function toPlainEnglishFromIastList(array $items, ?IastPlainEnglishOptions $options = null): array
{
    $options ??= new IastPlainEnglishOptions;
    return array_map(static fn(string $item): string => toPlainEnglishFromIast($item, $options), $items);
}

/**
 * Bulk converts an array of Devanagari strings back to canonical IAST.
 *
 * @param array<string> $items array of Devanagari strings
 * @param ScriptToIastOptions|null $options conversion options
 *
 * @return array<string> converted IAST strings
 */
function toCanonicalIastFromDevanagariList(array $items, ?ScriptToIastOptions $options = null): array
{
    $options ??= new ScriptToIastOptions;
    return array_map(static fn(string $item): string => toCanonicalIastFromDevanagari($item, $options), $items);
}

/**
 * Bulk converts an array of Gujarati strings back to canonical IAST.
 *
 * @param array<string> $items array of Gujarati strings
 * @param ScriptToIastOptions|null $options conversion options
 *
 * @return array<string> converted IAST strings
 */
function toCanonicalIastFromGujaratiList(array $items, ?ScriptToIastOptions $options = null): array
{
    $options ??= new ScriptToIastOptions;
    return array_map(static fn(string $item): string => toCanonicalIastFromGujarati($item, $options), $items);
}

/**
 * Bulk converts an array of Devanagari strings to canonical Gujarati.
 *
 * @param array<string> $items array of Devanagari strings
 * @param IndicScriptConversionOptions|null $options conversion options
 *
 * @return array<string> converted Gujarati strings
 */
function toCanonicalGujaratiFromDevanagariList(array $items, ?IndicScriptConversionOptions $options = null): array
{
    $options ??= new IndicScriptConversionOptions;
    return array_map(static fn(string $item): string => toCanonicalGujaratiFromDevanagari($item, $options), $items);
}

/**
 * Bulk converts an array of Gujarati strings to canonical Devanagari.
 *
 * @param array<string> $items array of Gujarati strings
 * @param IndicScriptConversionOptions|null $options conversion options
 *
 * @return array<string> converted Devanagari strings
 */
function toCanonicalDevanagariFromGujaratiList(array $items, ?IndicScriptConversionOptions $options = null): array
{
    $options ??= new IndicScriptConversionOptions;
    return array_map(static fn(string $item): string => toCanonicalDevanagariFromGujarati($item, $options), $items);
}

/**
 * Bulk recovers exact original Devanagari strings from an array of Gujarati strings.
 *
 * @param array<string> $items array of Gujarati strings
 *
 * @return array<string> converted Devanagari strings
 */
function toExactDevanagariFromGujaratiList(array $items): array
{
    return array_map(toExactDevanagariFromGujarati(...), $items);
}

/**
 * Bulk recovers exact original Gujarati strings from an array of Devanagari strings.
 *
 * @param array<string> $items array of Devanagari strings
 *
 * @return array<string> converted Gujarati strings
 */
function toExactGujaratiFromDevanagariList(array $items): array
{
    return array_map(toExactGujaratiFromDevanagari(...), $items);
}

/**
 * Bulk converts an array of IAST strings returning an array of Devanagari TransliterationResult envelopes.
 *
 * @param array<string> $items array of strings
 * @param DevanagariRomanizationProfile $profile romanization profile
 *
 * @return array<TransliterationResult> array of result envelopes
 */
function toDevanagariList(array $items, DevanagariRomanizationProfile $profile = DevanagariRomanizationProfile::EXTENDED_INDIC): array
{
    return array_map(static fn(string $item): TransliterationResult => toDevanagari($item, $profile), $items);
}

/**
 * Bulk converts an array of IAST strings returning an array of Gujarati TransliterationResult envelopes.
 *
 * @param array<string> $items array of strings
 * @param GujaratiRomanizationProfile $profile romanization profile
 *
 * @return array<TransliterationResult> array of result envelopes
 */
function toGujaratiList(array $items, GujaratiRomanizationProfile $profile = GujaratiRomanizationProfile::EXTENDED_INDIC): array
{
    return array_map(static fn(string $item): TransliterationResult => toGujarati($item, $profile), $items);
}

/**
 * Bulk converts an array of IAST strings returning an array of Plain English TransliterationResult envelopes.
 *
 * @param array<string> $items array of strings
 * @param PlainEnglishRomanizationProfile $profile romanization profile
 *
 * @return array<TransliterationResult> array of result envelopes
 */
function toPlainEnglishList(array $items, PlainEnglishRomanizationProfile $profile = PlainEnglishRomanizationProfile::EXTENDED_INDIC): array
{
    return array_map(static fn(string $item): TransliterationResult => toPlainEnglish($item, $profile), $items);
}
