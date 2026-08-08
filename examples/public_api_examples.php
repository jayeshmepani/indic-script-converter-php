#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Comprehensive public-API examples for jayeshmepani/lipimala (PHP).
 *
 * Covers envelope APIs, string converters, option permutations, reverse IAST,
 * direct Devanagari ↔ Gujarati, exact metadata recovery, and result envelopes.
 *
 * Run from php/:
 *   php examples/public_api_examples.php
 */

require_once __DIR__ . '/../autoload.php';

use Lipimala\DevanagariRomanizationProfile;
use Lipimala\EmbeddedExactSource;
use Lipimala\FinalAPolicy;
use Lipimala\GlottalStopPolicy;
use Lipimala\GujaratiRomanizationProfile;
use Lipimala\IastPlainEnglishOptions;
use Lipimala\IastToDevanagariAmbiguousLPolicy;
use Lipimala\IastToDevanagariDigitPolicy;
use Lipimala\IastToDevanagariOmPolicy;
use Lipimala\IastToDevanagariOptions;
use Lipimala\IastToDevanagariPunctuationPolicy;
use Lipimala\IastToDevanagariUnknownLatinPolicy;
use Lipimala\IastToGujaratiDigitPolicy;
use Lipimala\IastToGujaratiOmPolicy;
use Lipimala\IastToGujaratiOptions;
use Lipimala\IastToGujaratiPunctuationPolicy;
use Lipimala\IndicScriptConversionOptions;
use Lipimala\IndicScriptDigitPolicy;
use Lipimala\IndicScriptUnknownPolicy;
use Lipimala\JnaPolicy;
use Lipimala\NyaPolicy;
use Lipimala\PlainEnglishRomanizationProfile;
use Lipimala\ScriptToIastOptions;
use Lipimala\TransliterationResult;
use Lipimala\Unicode;
use Lipimala\UnicodeNormalizationForm;

use function Lipimala\embedExactSourceMetadata;
use function Lipimala\hasEmbeddedExactSource;
use function Lipimala\hasExactDevanagariScriptSourceMetadata;
use function Lipimala\hasExactGujaratiScriptSourceMetadata;
use function Lipimala\normalizeUnicode;
use function Lipimala\recoverEmbeddedExactSource;
use function Lipimala\stripExactSourceMetadata;
use function Lipimala\toCanonicalDevanagariFromGujarati;
use function Lipimala\toCanonicalGujaratiFromDevanagari;
use function Lipimala\toCanonicalGujaratiFromDevanagariList;
use function Lipimala\toCanonicalIastFromDevanagari;
use function Lipimala\toCanonicalIastFromGujarati;
use function Lipimala\toDevanagari;
use function Lipimala\toDevanagariFromGujarati;
use function Lipimala\toDevanagariFromIast;
use function Lipimala\toDevanagariFromIastList;
use function Lipimala\toDevanagariList;
use function Lipimala\toExactDevanagariFromGujarati;
use function Lipimala\toExactGujaratiFromDevanagari;
use function Lipimala\toExactIastFromDevanagari;
use function Lipimala\toExactIastFromGujarati;
use function Lipimala\toGujarati;
use function Lipimala\toGujaratiFromDevanagari;
use function Lipimala\toGujaratiFromIast;
use function Lipimala\toGujaratiFromIastList;
use function Lipimala\toIastFromDevanagari;
use function Lipimala\toIastFromGujarati;
use function Lipimala\toPlainEnglish;
use function Lipimala\toPlainEnglishFromIast;
use function Lipimala\toPlainEnglishFromIastList;
use function Lipimala\tryDecodeExactSourceMetadata;
use function Lipimala\visibleWithoutExactSourceMetadata;

const IAST = 'Kṛṣṇa ā́tman';
const VEDIC = 'vásōḥ';
const DIGITS = 'Rāma 123';
const PUNCT = 'namaḥ. śivāya.';
const OM = 'oṃ';
const AMBIG_L = 'kḷpta';
const PLAIN = 'jñāna Rāma ñāna';
const DEVA = 'कृष्ण';
const GUJR = 'કૃષ્ણ';

function banner(string $title): void
{
    echo PHP_EOL, str_repeat('=', 72), PHP_EOL, $title, PHP_EOL, str_repeat('=', 72), PHP_EOL;
}

function show(string $label, mixed $value): void
{
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_object($value) && enum_exists($value::class)) {
        $value = $value->value ?? (string) $value->name;
    } elseif (! is_scalar($value) && $value !== null) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    echo '  ', $label, ': ', $value, PHP_EOL;
}

// ---------------------------------------------------------------------------
// 1. Envelope APIs
// ---------------------------------------------------------------------------
function examplesEnvelope(): void
{
    banner('1. Envelope APIs (TransliterationResult)');

    $de = toDevanagari(IAST);
    $gu = toGujarati(IAST);
    $en = toPlainEnglish(IAST);

    foreach ([
        'toDevanagari' => $de,
        'toGujarati' => $gu,
        'toPlainEnglish' => $en,
    ] as $name => $result) {
        echo PHP_EOL, "[{$name}]", PHP_EOL;
        show('rendered', $result->rendered);
        show('profile', $result->profile->value);
        show('normalizedInput', $result->normalizedInput);
        show('restoreOriginal()', $result->restoreOriginal());
        show('renderingIsInjective', $result->renderingIsInjective);
        show('hasErrors', $result->hasErrors());
        show('issues[0].code', $result->issues[0]->code ?? null);
    }

    $jsonText = $de->toJsonText();
    $restored = TransliterationResult::fromJsonText($jsonText);
    show('JSON schema', $restored->toJson()['schema']);
    show('fromJsonText restore', $restored->restoreOriginal());

    echo PHP_EOL, '[normalization permutations]', PHP_EOL;
    foreach (UnicodeNormalizationForm::cases() as $inp) {
        foreach ([UnicodeNormalizationForm::NFC, UnicodeNormalizationForm::NFD] as $out) {
            $r = toDevanagari(IAST, null, $inp, $out);
            show("in={$inp->value} out={$out->value}", $r->rendered);
        }
    }
}

// ---------------------------------------------------------------------------
// 2. IAST → Devanagari
// ---------------------------------------------------------------------------
function examplesIastToDeva(): void
{
    banner('2. IAST → Devanagari (string) + option permutations');

    show('default', toDevanagariFromIast(IAST));

    foreach (DevanagariRomanizationProfile::cases() as $profile) {
        show("profile={$profile->value}", toDevanagariFromIast(IAST, new IastToDevanagariOptions(profile: $profile)));
    }

    foreach (IastToDevanagariDigitPolicy::cases() as $dig) {
        show("digitPolicy={$dig->value}", toDevanagariFromIast(DIGITS, new IastToDevanagariOptions(digitPolicy: $dig)));
    }

    foreach (IastToDevanagariPunctuationPolicy::cases() as $punc) {
        show(
            "punctuationPolicy={$punc->value}",
            toDevanagariFromIast(PUNCT, new IastToDevanagariOptions(punctuationPolicy: $punc)),
        );
    }

    foreach (IastToDevanagariOmPolicy::cases() as $om) {
        show("omPolicy={$om->value}", toDevanagariFromIast(OM, new IastToDevanagariOptions(omPolicy: $om)));
    }

    foreach (IastToDevanagariAmbiguousLPolicy::cases() as $amb) {
        show(
            "ambiguousLPolicy={$amb->value}",
            toDevanagariFromIast(AMBIG_L, new IastToDevanagariOptions(ambiguousLPolicy: $amb)),
        );
    }

    foreach (IastToDevanagariUnknownLatinPolicy::cases() as $unk) {
        try {
            show(
                "unknownLatinPolicy={$unk->value}",
                toDevanagariFromIast('hello', new IastToDevanagariOptions(unknownLatinPolicy: $unk)),
            );
        } catch (Throwable $e) {
            show("unknownLatinPolicy={$unk->value}", 'RAISED ' . $e::class . ': ' . $e->getMessage());
        }
    }

    show(
        'acceptAsciiLongVowels=true on aa',
        toDevanagariFromIast('aa', new IastToDevanagariOptions(acceptAsciiLongVowels: true)),
    );
    show(
        'collapseWhitespace=true',
        toDevanagariFromIast('Kṛṣṇa   ā́tman', new IastToDevanagariOptions(collapseWhitespace: true)),
    );
    show(
        'preserveVedicAccentMarks=false',
        toDevanagariFromIast(VEDIC, new IastToDevanagariOptions(preserveVedicAccentMarks: false)),
    );

    $tagged = toDevanagariFromIast('Om 12. Rāma', new IastToDevanagariOptions(
        profile: DevanagariRomanizationProfile::ISO_15919_CORE,
        digitPolicy: IastToDevanagariDigitPolicy::CONVERT_TO_SCRIPT,
        punctuationPolicy: IastToDevanagariPunctuationPolicy::INDIC_DANDA,
        omPolicy: IastToDevanagariOmPolicy::USE_OM_SIGN,
        acceptAsciiLongVowels: true,
        collapseWhitespace: true,
        embedExactSourceMetadata: true,
    ));
    show('combined options visible', stripExactSourceMetadata($tagged));
    show('has metadata', hasEmbeddedExactSource($tagged));
    show('exact reverse', toExactIastFromDevanagari($tagged));
}

// ---------------------------------------------------------------------------
// 3. IAST → Gujarati
// ---------------------------------------------------------------------------
function examplesIastToGujr(): void
{
    banner('3. IAST → Gujarati (string) + option permutations');

    show('default', toGujaratiFromIast(IAST));

    foreach (GujaratiRomanizationProfile::cases() as $profile) {
        show("profile={$profile->value}", toGujaratiFromIast(IAST, new IastToGujaratiOptions(profile: $profile)));
    }

    foreach (IastToGujaratiDigitPolicy::cases() as $dig) {
        show("digitPolicy={$dig->value}", toGujaratiFromIast(DIGITS, new IastToGujaratiOptions(digitPolicy: $dig)));
    }

    foreach (IastToGujaratiPunctuationPolicy::cases() as $punc) {
        show(
            "punctuationPolicy={$punc->value}",
            toGujaratiFromIast(PUNCT, new IastToGujaratiOptions(punctuationPolicy: $punc)),
        );
    }

    foreach (IastToGujaratiOmPolicy::cases() as $om) {
        show("omPolicy={$om->value}", toGujaratiFromIast(OM, new IastToGujaratiOptions(omPolicy: $om)));
    }

    $tagged = toGujaratiFromIast(IAST, new IastToGujaratiOptions(embedExactSourceMetadata: true));
    show('exact reverse from Gujr', toExactIastFromGujarati($tagged));
}

// ---------------------------------------------------------------------------
// 4. Plain English / Hunterian
// ---------------------------------------------------------------------------
function examplesPlainEnglish(): void
{
    banner('4. IAST → plain English / Hunterian');

    show('default', toPlainEnglishFromIast(PLAIN));

    foreach (PlainEnglishRomanizationProfile::cases() as $profile) {
        show(
            "profile={$profile->value}",
            toPlainEnglishFromIast(PLAIN, new IastPlainEnglishOptions(profile: $profile)),
        );
    }

    foreach (FinalAPolicy::cases() as $finalA) {
        show("finalA={$finalA->value}", toPlainEnglishFromIast('Rāma', new IastPlainEnglishOptions(finalA: $finalA)));
    }

    foreach (JnaPolicy::cases() as $jna) {
        show("jna={$jna->value}", toPlainEnglishFromIast('jñāna', new IastPlainEnglishOptions(jna: $jna)));
    }

    foreach (NyaPolicy::cases() as $nya) {
        show("nya={$nya->value}", toPlainEnglishFromIast('ñāna', new IastPlainEnglishOptions(nya: $nya)));
    }

    foreach (GlottalStopPolicy::cases() as $gl) {
        show(
            "glottalStop={$gl->value}",
            toPlainEnglishFromIast('aʔa', new IastPlainEnglishOptions(glottalStop: $gl)),
        );
    }

    show(
        'convertCToCh=false',
        toPlainEnglishFromIast('ca', new IastPlainEnglishOptions(convertCToCh: false)),
    );
    show(
        'hunterian envelope',
        toPlainEnglish(PLAIN, new IastPlainEnglishOptions(
            profile: PlainEnglishRomanizationProfile::HUNTERIAN,
        ))->rendered,
    );
}

// ---------------------------------------------------------------------------
// 5. Reverse
// ---------------------------------------------------------------------------
function examplesReverse(): void
{
    banner('5. Reverse Brahmic → IAST (canonical / smart / exact)');

    show('canonical Deva→IAST', toCanonicalIastFromDevanagari(DEVA));
    show('canonical Gujr→IAST', toCanonicalIastFromGujarati(GUJR));
    show('smart Deva→IAST (no trailer)', toIastFromDevanagari(DEVA));
    show('smart Gujr→IAST (no trailer)', toIastFromGujarati(GUJR));

    $taggedDe = toDevanagariFromIast('Kṛṣṇa', new IastToDevanagariOptions(embedExactSourceMetadata: true));
    $taggedGu = toGujaratiFromIast('Kṛṣṇa', new IastToGujaratiOptions(embedExactSourceMetadata: true));
    show('exact Deva→IAST', toExactIastFromDevanagari($taggedDe));
    show('exact Gujr→IAST', toExactIastFromGujarati($taggedGu));
    show('smart with trailer', toIastFromDevanagari($taggedDe));
    show(
        'ScriptToIastOptions preserveUnmapped',
        toCanonicalIastFromDevanagari(DEVA . '!', new ScriptToIastOptions(preserveUnmapped: true)),
    );
}

// ---------------------------------------------------------------------------
// 6. Direct script
// ---------------------------------------------------------------------------
function examplesDirectScript(): void
{
    banner('6. Direct Devanagari ↔ Gujarati');

    show('canonical Deva→Gujr', toCanonicalGujaratiFromDevanagari(DEVA));
    show('canonical Gujr→Deva', toCanonicalDevanagariFromGujarati(GUJR));
    show('smart Deva→Gujr', toGujaratiFromDevanagari(DEVA));
    show('smart Gujr→Deva', toDevanagariFromGujarati(GUJR));

    foreach (IndicScriptDigitPolicy::cases() as $dig) {
        show(
            "digitPolicy={$dig->value} on १२३",
            toCanonicalGujaratiFromDevanagari('१२३', new IndicScriptConversionOptions(digitPolicy: $dig)),
        );
    }

    foreach (IndicScriptUnknownPolicy::cases() as $unk) {
        try {
            show(
                "unknownPolicy={$unk->value}",
                toCanonicalGujaratiFromDevanagari('कृष्ण X', new IndicScriptConversionOptions(unknownPolicy: $unk)),
            );
        } catch (Throwable $e) {
            show("unknownPolicy={$unk->value}", 'RAISED ' . $e::class);
        }
    }

    $tagged = toCanonicalGujaratiFromDevanagari(
        'ऄ ऎ ऍ',
        new IndicScriptConversionOptions(embedExactSourceMetadata: true),
    );
    show('exact reverse Gujr→Deva', toExactDevanagariFromGujarati($tagged));

    $tagged2 = toCanonicalDevanagariFromGujarati(
        GUJR,
        new IndicScriptConversionOptions(embedExactSourceMetadata: true),
    );
    show('exact reverse Deva→Gujr', toExactGujaratiFromDevanagari($tagged2));
}

// ---------------------------------------------------------------------------
// 7. Metadata helpers
// ---------------------------------------------------------------------------
function examplesMetadata(): void
{
    banner('7. Exact-source metadata helpers & Unicode utilities');

    $rendered = toDevanagariFromIast(IAST);
    $tagged = embedExactSourceMetadata($rendered, IAST);
    show('hasEmbeddedExactSource', hasEmbeddedExactSource($tagged));
    show('recover', recoverEmbeddedExactSource($tagged));
    show('strip', stripExactSourceMetadata($tagged));
    show('visibleWithoutExactSourceMetadata', visibleWithoutExactSourceMetadata($tagged));

    $meta = tryDecodeExactSourceMetadata($tagged);
    if ($meta instanceof EmbeddedExactSource) {
        show('meta.originalSource', $meta->originalSource);
        show('meta.visibleText', $meta->visibleText);
    }

    $gujrTagged = toCanonicalGujaratiFromDevanagari('ऄ ऎ ऍ', new IndicScriptConversionOptions(embedExactSourceMetadata: true));
    show('hasExactGujaratiScriptSourceMetadata', hasExactGujaratiScriptSourceMetadata($gujrTagged));
    $devaTagged = toCanonicalDevanagariFromGujarati('અ એ ઍ', new IndicScriptConversionOptions(embedExactSourceMetadata: true));
    show('hasExactDevanagariScriptSourceMetadata', hasExactDevanagariScriptSourceMetadata($devaTagged));

    show('Unicode::isCombiningMark(0x0301)', Unicode::isCombiningMark(0x0301));
    show('normalize NFC', normalizeUnicode(IAST, UnicodeNormalizationForm::NFC));
    show('normalize NFD', normalizeUnicode(IAST, UnicodeNormalizationForm::NFD));
}

// ---------------------------------------------------------------------------
// 8. Bulk string array transliteration
// ---------------------------------------------------------------------------
function examplesBulkList(): void
{
    banner('8. Bulk String Array Transliteration (List API)');

    $items = ['Kṛṣṇa', 'Rāma', 'jñāna'];
    show('bulk IAST array', implode(', ', $items));
    show('toDevanagariFromIastList', implode(', ', toDevanagariFromIastList($items)));
    show('toGujaratiFromIastList', implode(', ', toGujaratiFromIastList($items)));
    show('toPlainEnglishFromIastList', implode(', ', toPlainEnglishFromIastList($items)));

    $devaList = toDevanagariFromIastList($items);
    show('toCanonicalGujaratiFromDevanagariList', implode(', ', toCanonicalGujaratiFromDevanagariList($devaList)));

    $envList = toDevanagariList($items);
    show('toDevanagariList (rendered)', implode(', ', array_map(fn(TransliterationResult $r): string => $r->rendered, $envList)));
}

echo 'lipimala — PHP public API examples', PHP_EOL;
examplesEnvelope();
examplesIastToDeva();
examplesIastToGujr();
examplesPlainEnglish();
examplesReverse();
examplesDirectScript();
examplesMetadata();
examplesBulkList();
echo PHP_EOL, 'Done. All public-API example sections executed.', PHP_EOL;
