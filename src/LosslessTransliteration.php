<?php

declare(strict_types=1);

namespace IndicScriptConverter;

require_once __DIR__ . '/LatnIastToDeva.php';
require_once __DIR__ . '/LatnIastToGujr.php';
require_once __DIR__ . '/LatnIastTranscription.php';
require_once __DIR__ . '/BrahmicToLatnIast.php';

function toLosslessDevanagari(
    string $text,
    ?IastToDevanagariOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    $options ??= new IastToDevanagariOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $directlyRendered = toDevanagariFromIast($normalizedInput, $options);
    $normalizedVisible = normalizeUnicode(stripExactSourceMetadata($directlyRendered), $outputNormalization);
    $rendered = $options->embedExactSourceMetadata
        ? embedExactSourceMetadata($normalizedVisible, $text)
        : $normalizedVisible;

    return new LosslessTransliterationResult(
        original: $text,
        normalizedInput: $normalizedInput,
        rendered: $rendered,
        profile: match ($options->profile) {
            DevanagariRomanizationProfile::STRICT_IAST => TransliterationProfile::STRICT_IAST,
            DevanagariRomanizationProfile::ISO_15919_CORE => TransliterationProfile::ISO_15919_CORE,
            DevanagariRomanizationProfile::EXTENDED_INDIC => TransliterationProfile::EXTENDED_INDIC,
        },
        inputNormalization: $inputNormalization,
        outputNormalization: $outputNormalization,
        renderingIsInjective: false,
        issues: [new TransliterationIssue(
            code: 'SOURCE_METADATA_REQUIRED_FOR_EXACT_REVERSE',
            message: 'Keep this envelope to recover exact source case, aliases, and code points.',
            severity: TransliterationIssueSeverity::INFO,
        )],
    );
}

function to_lossless_devanagari(
    string $text,
    ?IastToDevanagariOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    return toLosslessDevanagari($text, $options, $inputNormalization, $outputNormalization);
}

function toLosslessGujarati(
    string $text,
    ?IastToGujaratiOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    $options ??= new IastToGujaratiOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $directlyRendered = toGujaratiFromIast($normalizedInput, $options);
    $normalizedVisible = normalizeUnicode(stripExactSourceMetadata($directlyRendered), $outputNormalization);
    $rendered = $options->embedExactSourceMetadata
        ? embedExactSourceMetadata($normalizedVisible, $text)
        : $normalizedVisible;

    return new LosslessTransliterationResult(
        original: $text,
        normalizedInput: $normalizedInput,
        rendered: $rendered,
        profile: match ($options->profile) {
            GujaratiRomanizationProfile::STRICT_IAST => TransliterationProfile::STRICT_IAST,
            GujaratiRomanizationProfile::ISO_15919_CORE => TransliterationProfile::ISO_15919_CORE,
            GujaratiRomanizationProfile::EXTENDED_INDIC => TransliterationProfile::EXTENDED_INDIC,
        },
        inputNormalization: $inputNormalization,
        outputNormalization: $outputNormalization,
        renderingIsInjective: false,
        issues: [new TransliterationIssue(
            code: 'SOURCE_METADATA_REQUIRED_FOR_EXACT_REVERSE',
            message: 'Keep this envelope to recover exact source case, aliases, and code points.',
            severity: TransliterationIssueSeverity::INFO,
        )],
    );
}

function to_lossless_gujarati(
    string $text,
    ?IastToGujaratiOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    return toLosslessGujarati($text, $options, $inputNormalization, $outputNormalization);
}

function toLosslessPlainEnglish(
    string $text,
    ?IastPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    $options ??= new IastPlainEnglishOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $rendered = normalizeUnicode(toPlainEnglishFromIast($normalizedInput, $options), $outputNormalization);
    $isHunterian = $options->profile === PlainEnglishRomanizationProfile::HUNTERIAN;

    return new LosslessTransliterationResult(
        original: $text,
        normalizedInput: $normalizedInput,
        rendered: $rendered,
        profile: $isHunterian ? TransliterationProfile::HUNTERIAN : TransliterationProfile::PLAIN_ENGLISH,
        inputNormalization: $inputNormalization,
        outputNormalization: $outputNormalization,
        renderingIsInjective: false,
        issues: [new TransliterationIssue(
            code: $isHunterian ? 'HUNTERIAN_VIEW_IS_INTRINSICALLY_LOSSY' : 'PLAIN_ENGLISH_VIEW_IS_INTRINSICALLY_LOSSY',
            message: $isHunterian
                ? 'Hunterian merges vowel length, place of articulation, and other distinctions. Exact recovery uses the retained source envelope.'
                : 'Plain-English rendering merges scholarly distinctions. Exact recovery uses the retained source envelope.',
            severity: TransliterationIssueSeverity::INFO,
        )],
    );
}

function to_lossless_plain_english(
    string $text,
    ?IastPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): LosslessTransliterationResult {
    return toLosslessPlainEnglish($text, $options, $inputNormalization, $outputNormalization);
}
