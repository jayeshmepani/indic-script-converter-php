<?php

declare(strict_types=1);

namespace Lipimala;

require_once __DIR__ . '/LatnIastToDeva.php';
require_once __DIR__ . '/LatnIastToGujr.php';
require_once __DIR__ . '/LatnIastTranscription.php';
require_once __DIR__ . '/BrahmicToLatnIast.php';

function toDevanagari(
    string $text,
    ?IastToDevanagariOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    $options ??= new IastToDevanagariOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $directlyRendered = toDevanagariFromIast($normalizedInput, $options);
    $normalizedVisible = normalizeUnicode(stripExactSourceMetadata($directlyRendered), $outputNormalization);
    $rendered = $options->embedExactSourceMetadata
        ? embedExactSourceMetadata($normalizedVisible, $text)
        : $normalizedVisible;

    return new TransliterationResult(
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

function to_devanagari(
    string $text,
    ?IastToDevanagariOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toDevanagari($text, $options, $inputNormalization, $outputNormalization);
}

function toGujarati(
    string $text,
    ?IastToGujaratiOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    $options ??= new IastToGujaratiOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $directlyRendered = toGujaratiFromIast($normalizedInput, $options);
    $normalizedVisible = normalizeUnicode(stripExactSourceMetadata($directlyRendered), $outputNormalization);
    $rendered = $options->embedExactSourceMetadata
        ? embedExactSourceMetadata($normalizedVisible, $text)
        : $normalizedVisible;

    return new TransliterationResult(
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

function to_gujarati(
    string $text,
    ?IastToGujaratiOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toGujarati($text, $options, $inputNormalization, $outputNormalization);
}

function toPlainEnglish(
    string $text,
    ?IastPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    $options ??= new IastPlainEnglishOptions;
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $rendered = normalizeUnicode(toPlainEnglishFromIast($normalizedInput, $options), $outputNormalization);
    $isHunterian = $options->profile === PlainEnglishRomanizationProfile::HUNTERIAN;

    return new TransliterationResult(
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

function to_plain_english(
    string $text,
    ?IastPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toPlainEnglish($text, $options, $inputNormalization, $outputNormalization);
}

/**
 * Builds a loss-aware result envelope for a direct Brahmic -> Latin view.
 */
function brahmicPlainEnglishResult(
    string $text,
    callable $renderer,
    TransliterationProfile $profile,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    $normalizedInput = normalizeUnicode($text, $inputNormalization);
    $rendered = normalizeUnicode(
        $options instanceof BrahmicPlainEnglishOptions ? $renderer($normalizedInput, $options) : $renderer($normalizedInput),
        $outputNormalization,
    );

    return new TransliterationResult(
        original: $text,
        normalizedInput: $normalizedInput,
        rendered: $rendered,
        profile: $profile,
        inputNormalization: $inputNormalization,
        outputNormalization: $outputNormalization,
        renderingIsInjective: false,
        issues: [new TransliterationIssue(
            code: $profile === TransliterationProfile::HUNTERIAN
                ? 'BRAHMIC_HUNTERIAN_VIEW_IS_INTRINSICALLY_LOSSY'
                : 'BRAHMIC_PLAIN_ENGLISH_VIEW_IS_INTRINSICALLY_LOSSY',
            message: 'The rendered Latin view is pronunciation-oriented and lossy; the result envelope retains the exact Brahmic source.',
            severity: TransliterationIssueSeverity::INFO,
        )],
    );
}

function toPlainEnglishFromDevanagariResult(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return brahmicPlainEnglishResult(
        $text,
        toPlainEnglishFromDevanagari(...),
        TransliterationProfile::PLAIN_ENGLISH,
        $options,
        $inputNormalization,
        $outputNormalization,
    );
}

function toHunterianFromDevanagariResult(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return brahmicPlainEnglishResult(
        $text,
        toHunterianFromDevanagari(...),
        TransliterationProfile::HUNTERIAN,
        $options,
        $inputNormalization,
        $outputNormalization,
    );
}

function toPlainEnglishFromGujaratiResult(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return brahmicPlainEnglishResult(
        $text,
        toPlainEnglishFromGujarati(...),
        TransliterationProfile::PLAIN_ENGLISH,
        $options,
        $inputNormalization,
        $outputNormalization,
    );
}

function toHunterianFromGujaratiResult(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return brahmicPlainEnglishResult(
        $text,
        toHunterianFromGujarati(...),
        TransliterationProfile::HUNTERIAN,
        $options,
        $inputNormalization,
        $outputNormalization,
    );
}

function to_plain_english_from_devanagari_result(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toPlainEnglishFromDevanagariResult($text, $options, $inputNormalization, $outputNormalization);
}

function to_hunterian_from_devanagari_result(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toHunterianFromDevanagariResult($text, $options, $inputNormalization, $outputNormalization);
}

function to_plain_english_from_gujarati_result(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toPlainEnglishFromGujaratiResult($text, $options, $inputNormalization, $outputNormalization);
}

function to_hunterian_from_gujarati_result(
    string $text,
    ?BrahmicPlainEnglishOptions $options = null,
    UnicodeNormalizationForm $inputNormalization = UnicodeNormalizationForm::NFD,
    UnicodeNormalizationForm $outputNormalization = UnicodeNormalizationForm::NFC,
): TransliterationResult {
    return toHunterianFromGujaratiResult($text, $options, $inputNormalization, $outputNormalization);
}
