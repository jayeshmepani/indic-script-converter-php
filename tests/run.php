<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_latn_iast.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_deva.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_gujr.php';

use Lipimala\DevanagariRomanizationProfile;
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
use Lipimala\IastToGujaratiAmbiguousLPolicy;
use Lipimala\IastToGujaratiDigitPolicy;
use Lipimala\IastToGujaratiOmPolicy;
use Lipimala\IastToGujaratiOptions;
use Lipimala\IastToGujaratiPunctuationPolicy;
use Lipimala\IastToGujaratiUnknownLatinPolicy;
use Lipimala\IndicScriptConversionOptions;
use Lipimala\IndicScriptDigitPolicy;
use Lipimala\IndicScriptUnknownPolicy;
use Lipimala\JnaPolicy;
use Lipimala\NyaPolicy;
use Lipimala\PlainEnglishRomanizationProfile;
use Lipimala\ScriptToIastOptions;
use Lipimala\TransliterationResult;
use Lipimala\Unicode;
use Lipimala\UnicodeData;
use Lipimala\UnicodeNormalizationForm;

use function Lipimala\embedExactSourceMetadata;
use function Lipimala\hasEmbeddedExactSource;
use function Lipimala\recoverEmbeddedExactSource;
use function Lipimala\stripExactSourceMetadata;
use function Lipimala\toCanonicalDevanagariFromGujarati;
use function Lipimala\toCanonicalGujaratiFromDevanagari;
use function Lipimala\toCanonicalIastFromDevanagari;
use function Lipimala\toCanonicalIastFromGujarati;
use function Lipimala\toDevanagariFromGujarati;
use function Lipimala\toDevanagariFromIast;
use function Lipimala\toExactDevanagariFromGujarati;
use function Lipimala\toExactGujaratiFromDevanagari;
use function Lipimala\toExactIastFromDevanagari;
use function Lipimala\toExactIastFromGujarati;
use function Lipimala\toGujaratiFromDevanagari;
use function Lipimala\toGujaratiFromIast;
use function Lipimala\toPlainEnglish;
use function Lipimala\toPlainEnglishFromIast;

use const Lipimala\Verification\DEVANAGARI_SMOKE_SAMPLES;
use const Lipimala\Verification\GUJARATI_SMOKE_SAMPLES;
use const Lipimala\Verification\TRANSLITERATION_SMOKE_SAMPLES;
use const Lipimala\Verification\VEDIC_ROUND_TRIP_CASES;

final class TestFailure extends RuntimeException {}

$assertions = 0;
$tests = 0;

function same(mixed $actual, mixed $expected, string $message = ''): void
{
    global $assertions;
    ++$assertions;
    if ($actual !== $expected) {
        throw new TestFailure(
            ($message !== '' ? $message . PHP_EOL : '')
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true),
        );
    }
}

function truthy(bool $condition, string $message = ''): void
{
    same($condition, true, $message);
}

function throws(callable $callback, string $message = ''): void
{
    global $assertions;
    ++$assertions;
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new TestFailure($message !== '' ? $message : 'Expected exception was not thrown.');
}

function test(string $name, callable $callback): void
{
    global $tests;
    ++$tests;
    try {
        $callback();
        echo '✓ ', $name, PHP_EOL;
    } catch (Throwable $throwable) {
        fwrite(STDERR, '✗ ' . $name . PHP_EOL . $throwable->getMessage() . PHP_EOL);
        exit(1);
    }
}

/**
 * @param list<string> $sources @param list<string> $expected
 */
function assertCorpus(string $label, array $sources, array $expected, callable $convert): void
{
    same(count($sources), count($expected), "$label length");
    foreach ($sources as $index => $source) {
        same($convert($source), $expected[$index], "$label mismatch at index $index: " . json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}

$oracle = json_decode(file_get_contents(__DIR__ . '/oracle-data.json'), true, 512, JSON_THROW_ON_ERROR);
$directOracle = json_decode(file_get_contents(__DIR__ . '/direct-oracle.json'), true, 512, JSON_THROW_ON_ERROR);

test('PHP runtime is 8.3 or newer', static function (): void {
    truthy(PHP_VERSION_ID >= 80300, 'PHP 8.3+ is required.');
});

test('bundled Unicode database is Unicode 17.0.0', static function (): void {
    same(UnicodeData::VERSION, '17.0.0');
    same(Unicode::nfd('ṛ'), "r\u{0323}");
    same(Unicode::nfc("r\u{0323}"), 'ṛ');
});

test('default profiles are extendedIndic', static function (): void {
    same((new IastToDevanagariOptions)->profile, DevanagariRomanizationProfile::EXTENDED_INDIC);
    same((new IastToGujaratiOptions)->profile, GujaratiRomanizationProfile::EXTENDED_INDIC);
    same((new IastPlainEnglishOptions)->profile, PlainEnglishRomanizationProfile::EXTENDED_INDIC);
});

test('Latin/IAST → Devanagari matches the 497-case Dart oracle', static function () use ($oracle): void {
    $options = new IastToDevanagariOptions(
        digitPolicy: IastToDevanagariDigitPolicy::CONVERT_TO_SCRIPT,
        punctuationPolicy: IastToDevanagariPunctuationPolicy::INDIC_DANDA,
    );
    assertCorpus('deva', $oracle['latin'], $oracle['expectedDeva'], static fn(string $s): string => toDevanagariFromIast($s, $options));
});

test('Latin/IAST → Gujarati matches the 497-case Dart oracle', static function () use ($oracle): void {
    $options = new IastToGujaratiOptions(
        digitPolicy: IastToGujaratiDigitPolicy::CONVERT_TO_SCRIPT,
        punctuationPolicy: IastToGujaratiPunctuationPolicy::INDIC_DANDA,
    );
    assertCorpus('gujr', $oracle['latin'], $oracle['expectedGujr'], static fn(string $s): string => toGujaratiFromIast($s, $options));
});

test('Latin/IAST → plain English matches the 497-case Dart oracle', static function () use ($oracle): void {
    assertCorpus('plain', $oracle['latin'], $oracle['expectedPlain'], static fn(string $s): string => toPlainEnglishFromIast($s));
});

test('Devanagari → canonical IAST matches the 497-case Dart oracle', static function () use ($oracle): void {
    assertCorpus('deva reverse', $oracle['devaSource'], $oracle['expectedDevaReverse'], static fn(string $s): string => toCanonicalIastFromDevanagari($s));
});

test('Gujarati → canonical IAST matches the 497-case Dart oracle', static function () use ($oracle): void {
    assertCorpus('gujr reverse', $oracle['gujrSource'], $oracle['expectedGujrReverse'], static fn(string $s): string => toCanonicalIastFromGujarati($s));
});

test('all 22 Vedic Devanagari fixtures match exactly', static function (): void {
    $options = new IastToDevanagariOptions(
        digitPolicy: IastToDevanagariDigitPolicy::CONVERT_TO_SCRIPT,
        punctuationPolicy: IastToDevanagariPunctuationPolicy::INDIC_DANDA,
    );
    foreach (VEDIC_ROUND_TRIP_CASES as [$iast, $expected, $label]) {
        same(toDevanagariFromIast($iast, $options), $expected, $label);
    }
});

test('Devanagari profiles match Dart', static function (): void {
    $samples = ['ṛaka', 'ṛhaṇa', 'ḷa', 'laṛkā', 'xaṇḍa'];
    $expected = [
        DevanagariRomanizationProfile::STRICT_IAST->value => ['ऋअक', 'ऋहण', 'ऌअ', 'लऋका', 'xअण्ड'],
        DevanagariRomanizationProfile::ISO_15919_CORE->value => ['ड़क', 'ढ़ण', 'ळ', 'लड़का', 'ख़ण्ड'],
        DevanagariRomanizationProfile::EXTENDED_INDIC->value => ['ड़क', 'ढ़ण', 'ळ', 'लड़का', 'ख़ण्ड'],
    ];
    foreach (DevanagariRomanizationProfile::cases() as $profile) {
        $options = new IastToDevanagariOptions(profile: $profile);
        same(array_map(static fn(string $s): string => toDevanagariFromIast($s, $options), $samples), $expected[$profile->value], $profile->value);
    }
});

test('Gujarati profiles match Dart', static function (): void {
    $samples = ['ṛaka', 'ṛhaṇa', 'ḷa', 'laṛkā', 'xaṇḍa'];
    $expected = [
        GujaratiRomanizationProfile::STRICT_IAST->value => ['ઋઅક', 'ઋહણ', 'ઌઅ', 'લઋકા', 'xઅણ્ડ'],
        GujaratiRomanizationProfile::ISO_15919_CORE->value => ['ડ઼ક', 'ઢ઼ણ', 'ળ', 'લડ઼કા', 'ખ઼ણ્ડ'],
        GujaratiRomanizationProfile::EXTENDED_INDIC->value => ['ડ઼ક', 'ઢ઼ણ', 'ળ', 'લડ઼કા', 'ખ઼ણ્ડ'],
    ];
    foreach (GujaratiRomanizationProfile::cases() as $profile) {
        $options = new IastToGujaratiOptions(profile: $profile);
        same(array_map(static fn(string $s): string => toGujaratiFromIast($s, $options), $samples), $expected[$profile->value], $profile->value);
    }
});

test('digits, danda and OM options match Dart', static function (): void {
    same(toDevanagariFromIast('12345', new IastToDevanagariOptions(digitPolicy: IastToDevanagariDigitPolicy::CONVERT_TO_SCRIPT)), '१२३४५');
    same(toGujaratiFromIast('12345', new IastToGujaratiOptions(digitPolicy: IastToGujaratiDigitPolicy::CONVERT_TO_SCRIPT)), '૧૨૩૪૫');
    same(toDevanagariFromIast('End. Double end..', new IastToDevanagariOptions(punctuationPolicy: IastToDevanagariPunctuationPolicy::INDIC_DANDA)), 'एन्द्। दोउब्ले एन्द्।।');
    same(toGujaratiFromIast('End. Double end..', new IastToGujaratiOptions(punctuationPolicy: IastToGujaratiPunctuationPolicy::INDIC_DANDA)), 'એન્દ્। દોઉબ્લે એન્દ્।।');
    same(toDevanagariFromIast('oṃ namaḥ śivāya', new IastToDevanagariOptions(omPolicy: IastToDevanagariOmPolicy::USE_OM_SIGN)), 'ॐ नमः शिवाय');
    same(toGujaratiFromIast('oṃ namaḥ śivāya', new IastToGujaratiOptions(omPolicy: IastToGujaratiOmPolicy::USE_OM_SIGN)), 'ૐ નમઃ શિવાય');
});

test('plain-English policies and Hunterian samples match Dart', static function (): void {
    $base = ['Rāma', 'vrata', 'Kṛṣṇa', 'Lakṣmaṇa', 'yātrā'];
    $keep = new IastPlainEnglishOptions(finalA: FinalAPolicy::KEEP);
    $drop = new IastPlainEnglishOptions(finalA: FinalAPolicy::DROP);
    $jna = new IastPlainEnglishOptions(jna: JnaPolicy::JNA);
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $keep), $base), ['Rama','vrata','Krishna','Lakshmana','yatra']);
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $drop), $base), ['Ram','vrat','Krishn','Lakshman','yatra']);
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $jna), ['jñāna','yajña']), ['jnan','yajn']);

    $hunterian = new IastPlainEnglishOptions(profile: PlainEnglishRomanizationProfile::HUNTERIAN);
    $samples = ['Rāma','Kṛṣṇa','Lakṣmaṇa','laṛkā','Rāmacandra','Gorakhapura','Sarasvatī','Īśvara','pañcāṅga','duḥkha','Devadatta','Jaideva','Kalyāṇapura','Nārāyaṇapura','Hariprasāda','Kṛṣṇadāsa'];
    $expected = ['Ram','Krishna','Lakshman','larka','Ramachandra','Gorakhapur','Saraswati','Ishwar','panchang','dukh','Devadatt','Jaidev','Kalyanapur','Narayanapur','Hariprasad','Krishnadas'];
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $hunterian), $samples), $expected);
});

test('all forward option switches preserve source semantics', static function (): void {
    same(toDevanagariFromIast("a\u{036F}", new IastToDevanagariOptions(unknownLatinPolicy: IastToDevanagariUnknownLatinPolicy::PASS_THROUGH)), "अ\u{036F}");
    same(toDevanagariFromIast("a\u{036F}", new IastToDevanagariOptions(unknownLatinPolicy: IastToDevanagariUnknownLatinPolicy::BRACKET)), "अ[\u{036F}]");
    throws(static fn(): string => toDevanagariFromIast("a\u{036F}", new IastToDevanagariOptions(unknownLatinPolicy: IastToDevanagariUnknownLatinPolicy::THROW_ERROR)));
    same(toGujaratiFromIast("a\u{036F}", new IastToGujaratiOptions(unknownLatinPolicy: IastToGujaratiUnknownLatinPolicy::PASS_THROUGH)), "અ\u{036F}");
    same(toGujaratiFromIast("a\u{036F}", new IastToGujaratiOptions(unknownLatinPolicy: IastToGujaratiUnknownLatinPolicy::BRACKET)), "અ[\u{036F}]");
    throws(static fn(): string => toGujaratiFromIast("a\u{036F}", new IastToGujaratiOptions(unknownLatinPolicy: IastToGujaratiUnknownLatinPolicy::THROW_ERROR)));

    same(toDevanagariFromIast('ḷa ḷkāra', new IastToDevanagariOptions(ambiguousLPolicy: IastToDevanagariAmbiguousLPolicy::CONTEXT)), 'ळ ऌकार');
    same(toDevanagariFromIast('ḷa ḷkāra', new IastToDevanagariOptions(ambiguousLPolicy: IastToDevanagariAmbiguousLPolicy::PREFER_VOCALIC)), 'ळ ऌकार');
    same(toDevanagariFromIast('ḷa ḷkāra', new IastToDevanagariOptions(ambiguousLPolicy: IastToDevanagariAmbiguousLPolicy::PREFER_CONSONANT)), 'ळ ळ्कार');
    same(toGujaratiFromIast('ḷa ḷkāra', new IastToGujaratiOptions(ambiguousLPolicy: IastToGujaratiAmbiguousLPolicy::CONTEXT)), 'ળ ઌકાર');

    same(toDevanagariFromIast('aa ii uu rr ll'), 'अअ इइ उउ र्र् ल्ल्');
    same(toDevanagariFromIast('aa ii uu rr ll', new IastToDevanagariOptions(acceptAsciiLongVowels: true)), 'आ ई ऊ ॠ ॡ');
    same(toGujaratiFromIast('aa ii uu rr ll', new IastToGujaratiOptions(acceptAsciiLongVowels: true)), 'આ ઈ ઊ ૠ ૡ');

    same(toDevanagariFromIast('xaṇḍa', new IastToDevanagariOptions(acceptPlainXAsKha: false)), 'xअण्ड');
    same(toGujaratiFromIast('xaṇḍa', new IastToGujaratiOptions(acceptPlainXAsKha: false)), 'xઅણ્ડ');
    same(toDevanagariFromIast('weda', new IastToDevanagariOptions(acceptWAsVa: false)), 'wएद');
    same(toGujaratiFromIast('weda', new IastToGujaratiOptions(acceptWAsVa: false)), 'wએદ');
    same(toDevanagariFromIast('shakti', new IastToDevanagariOptions(acceptPlainSh: false)), 'sहक्ति');
    same(toGujaratiFromIast('shakti', new IastToGujaratiOptions(acceptPlainSh: false)), 'sહક્તિ');

    same(toDevanagariFromIast('agním'), 'अग्नि॑म्');
    same(toDevanagariFromIast('agním', new IastToDevanagariOptions(preserveVedicAccentMarks: false)), 'अग्निम्');
    same(toGujaratiFromIast('agním'), 'અગ્નિ॑મ્');
    same(toGujaratiFromIast('agním', new IastToGujaratiOptions(preserveVedicAccentMarks: false)), 'અગ્નિમ્');

    same(toDevanagariFromIast("  śiva   rāma\n kṛṣṇa  ", new IastToDevanagariOptions(collapseWhitespace: true)), 'शिव राम कृष्ण');
    same(toGujaratiFromIast("  śiva   rāma\n kṛṣṇa  ", new IastToGujaratiOptions(collapseWhitespace: true)), 'શિવ રામ કૃષ્ણ');
});

test('all plain-English option switches preserve source semantics', static function (): void {
    same(toPlainEnglishFromIast('ñāna', new IastPlainEnglishOptions(nya: NyaPolicy::NA)), 'nan');
    same(toPlainEnglishFromIast('ñāna', new IastPlainEnglishOptions(nya: NyaPolicy::NYA)), 'nyan');
    same(toPlainEnglishFromIast('ñāna', new IastPlainEnglishOptions(nya: NyaPolicy::GNA)), 'gnan');
    same(toPlainEnglishFromIast('ʔāgama'), 'agam');
    same(toPlainEnglishFromIast('ʔāgama', new IastPlainEnglishOptions(glottalStop: GlottalStopPolicy::APOSTROPHE)), "'agam");
    same(toPlainEnglishFromIast('candra chaya'), 'chandra chhaya');
    same(toPlainEnglishFromIast('candra chaya', new IastPlainEnglishOptions(convertCToCh: false)), 'candra chaya');
    same(toPlainEnglishFromIast('saṃkalpa oṃ'), 'sankalp om');
    same(toPlainEnglishFromIast('saṃkalpa oṃ', new IastPlainEnglishOptions(assimilateAnusvara: false)), 'samkalp om');
    same(toPlainEnglishFromIast("so'ham 'jñāna'"), "soham 'gyan'");
    same(toPlainEnglishFromIast("so'ham 'jñāna'", new IastPlainEnglishOptions(removeAvagraha: false)), "so'ham 'gyan'");
    same(toPlainEnglishFromIast("  śiva   rāma\n kṛṣṇa  ", new IastPlainEnglishOptions(collapseWhitespace: true)), 'shiv ram krishna');
    same(toPlainEnglishFromIast('Gorakhapura Hariprasāda', new IastPlainEnglishOptions(enableInternalSchwaSyncope: true)), 'Gorakhpur Hariprasd');
    same(toPlainEnglishFromIast('Sarasvatī'), 'Sarasvati');
    same(toPlainEnglishFromIast('Sarasvatī', new IastPlainEnglishOptions(useWForVAfterConsonants: true)), 'Saraswati');
    same(toPlainEnglishFromIast('agním agnìm'), 'agnim agnim');
    same(toPlainEnglishFromIast('agním agnìm', new IastPlainEnglishOptions(preserveVedicAccentMarks: true)), "agni\u{0301}m agni\u{0300}m");
    same(toPlainEnglishFromIast('vrata dharma', new IastPlainEnglishOptions(keepFinalAForWords: ['vrata'])), 'vrata dharma');
});

test('reverse options for unmapped characters, Vedic marks and normalization', static function (): void {
    same(toCanonicalIastFromDevanagari('वसोः॑ X €'), 'vasóḥ X €');
    same(toCanonicalIastFromDevanagari('वसोः॑ X €', new ScriptToIastOptions(preserveUnmapped: false)), 'vasóḥ');
    same(toCanonicalIastFromDevanagari('वसोः॑', new ScriptToIastOptions(preserveEncodedVedicMarks: false)), 'vasóḥ');
    same(toCanonicalIastFromDevanagari('क़', new ScriptToIastOptions(outputNormalization: UnicodeNormalizationForm::NFD)), 'qa');
    same(toCanonicalIastFromGujarati('વસોઃ॑ X €'), 'vasóḥ X €');
    same(toCanonicalIastFromGujarati('વસોઃ॑ X €', new ScriptToIastOptions(preserveUnmapped: false)), 'vasóḥ');
    same(toCanonicalIastFromGujarati('વસોઃ॑', new ScriptToIastOptions(preserveEncodedVedicMarks: false)), 'vasóḥ');
    same(toCanonicalIastFromGujarati('ક઼', new ScriptToIastOptions(outputNormalization: UnicodeNormalizationForm::NFD)), 'qa');
});

test('direct converter policies for digits, unknown characters and whitespace', static function (): void {
    same(toCanonicalGujaratiFromDevanagari('कृष्ण १२३ €'), 'કૃષ્ણ ૧૨૩ €');
    same(toCanonicalGujaratiFromDevanagari('कृष्ण १२३ €', new IndicScriptConversionOptions(digitPolicy: IndicScriptDigitPolicy::PRESERVE_SOURCE)), 'કૃષ્ણ १२३ €');
    throws(static fn(): string => toCanonicalGujaratiFromDevanagari('कृष्ण €', new IndicScriptConversionOptions(unknownPolicy: IndicScriptUnknownPolicy::THROW_ERROR)));
    same(toCanonicalGujaratiFromDevanagari("  कृष्ण   राम\n शिव  ", new IndicScriptConversionOptions(collapseWhitespace: true)), 'કૃષ્ણ રામ શિવ');
    same(toCanonicalDevanagariFromGujarati('કૃષ્ણ ૧૨૩ €'), 'कृष्ण १२३ €');
    same(toCanonicalDevanagariFromGujarati('કૃષ્ણ ૧૨૩ €', new IndicScriptConversionOptions(digitPolicy: IndicScriptDigitPolicy::PRESERVE_SOURCE)), 'कृष्ण ૧૨૩ €');
    throws(static fn(): string => toCanonicalDevanagariFromGujarati('કૃષ્ણ €', new IndicScriptConversionOptions(unknownPolicy: IndicScriptUnknownPolicy::THROW_ERROR)));
    same(toCanonicalDevanagariFromGujarati("  કૃષ્ણ   રામ\n શિવ  ", new IndicScriptConversionOptions(collapseWhitespace: true)), 'कृष्ण राम शिव');
});

test('exact Devanagari metadata round-trips every 497-case source key', static function (): void {
    $options = new IastToDevanagariOptions(embedExactSourceMetadata: true);
    foreach (TRANSLITERATION_SMOKE_SAMPLES as $index => $source) {
        $tagged = toDevanagariFromIast($source, $options);
        if ($source !== '') {
            truthy(hasEmbeddedExactSource($tagged), "metadata missing at $index");
        }

        same(toExactIastFromDevanagari($tagged), $source, "exact deva $index");
    }
});

test('exact Gujarati metadata round-trips every 497-case source key', static function (): void {
    $options = new IastToGujaratiOptions(embedExactSourceMetadata: true);
    foreach (TRANSLITERATION_SMOKE_SAMPLES as $index => $source) {
        $tagged = toGujaratiFromIast($source, $options);
        if ($source !== '') {
            truthy(hasEmbeddedExactSource($tagged), "metadata missing at $index");
        }

        same(toExactIastFromGujarati($tagged), $source, "exact gujr $index");
    }
});

test('metadata format is byte-for-byte compatible with Dart/Python/JavaScript, including isolated UTF-16 surrogate units', static function (): void {
    $source = base64_decode('S+G5m+G5o+G5h2EgLyBLcsyl4bmj4bmHYSAvIOG4q8SBbmEgLyDhuaPMgWFrdGkgLyDwkI2IIC8g7aCA', true);
    $rendered = base64_decode('4KSV4KWD4KS34KWN4KSjIC8g4KSV4KWD4KS34KWN4KSjIC8g4KSW4KS84KS+4KSoIC8g4KS34KWR4KSV4KWN4KSk4KS/IC8g8JCNiCAvIO2ggA==', true);
    $tagged = base64_decode('4KSV4KWD4KS34KWN4KSjIC8g4KSV4KWD4KS34KWN4KSjIC8g4KSW4KS84KS+4KSoIC8g4KS34KWR4KSV4KWN4KSk4KS/IC8g8JCNiCAvIO2ggPOggIHzoIGM86CBifOggZTzoICx86CAuvOggZPzoIG386CBgvOggaLzoIGI86CBrfOggY3zoIGl86CBkvOggbjzoIC186CBqPOggYHzoIGD86CBgfOggYHzoIGM86CBt/OggYHzoIGn86CBgfOggYXzoIGz86CBgfOggaPzoIGn86CBgfOggazzoIGB86CAsvOggY3zoIGl86CBkvOggbjzoIC186CBqPOggYHzoIGD86CBgfOggYHzoIGM86CBt/OggYHzoIGn86CBgfOggYPzoIGz86CBpfOggYHzoIGR86CBhvOggbXzoIGB86CBh/OggYXzoIGB86CBifOggYHzoIGB86CBtvOggYHzoIGD86CBgfOggYHzoIGZ86CBuPOggLTzoIGC86CBgfOggLLzoIGF86CBgfOggaHzoIG386CBgvOggLDzoIGB86CBh/OggavzoIGB86CBifOggYHzoIGB86CBtvOggYHzoIGD86CBgfOggYHzoIGB86CBjvOggajzoIGJ86CAs/OggbnzoIGB86CBgfOggYzzoIG386CBgfOggafzoIGB86CBgfOggYTzoIGZ86CAuvOggLTzoIGk86CBpvOggLXzoIC086CBpvOggLHzoIGl86CAuvOggLPzoICy86CAt/OggLnzoIGi86CBpPOggLXzoIGl86CBvw==', true);
    if ($source === false || $rendered === false || $tagged === false) {
        throw new TestFailure('Invalid embedded test vector.');
    }

    same(embedExactSourceMetadata($rendered, $source), $tagged);
    same(recoverEmbeddedExactSource($tagged), $source);
});

test('visible and metadata tampering invalidates exact recovery', static function (): void {
    $tagged = toDevanagariFromIast('Kṛṣṇa', new IastToDevanagariOptions(embedExactSourceMetadata: true));
    same(stripExactSourceMetadata($tagged), 'कृष्ण');
    $chars = Unicode::codePoints($tagged);
    $chars[0] = Unicode::ord('X');
    same(hasEmbeddedExactSource(Unicode::fromCodePoints($chars)), false);

    $tagged = toGujaratiFromIast('Kṛṣṇa', new IastToGujaratiOptions(embedExactSourceMetadata: true));
    $chars = Unicode::codePoints($tagged);
    $chars[count($chars) - 2] += 1;
    same(hasEmbeddedExactSource(Unicode::fromCodePoints($chars)), false);
});

test('exact round-trip envelope JSON round-trip', static function (): void {
    $result = toPlainEnglish('Kṛṣṇa ā́tman ḷa');
    $restored = TransliterationResult::fromJsonText($result->toJsonText());
    same($restored->toJson(), $result->toJson());
    same($restored->restoreOriginal(), 'Kṛṣṇa ā́tman ḷa');
});

test('Devanagari → Gujarati matches the 497-case direct JavaScript/Python oracle', static function () use ($oracle, $directOracle): void {
    assertCorpus('deva->gujr', $oracle['devaSource'], $directOracle['devaToGujr'], static fn(string $s): string => toCanonicalGujaratiFromDevanagari($s));
});

test('Gujarati → Devanagari matches the 497-case direct JavaScript/Python oracle', static function () use ($oracle, $directOracle): void {
    assertCorpus('gujr->deva', $oracle['gujrSource'], $directOracle['gujrToDeva'], static fn(string $s): string => toCanonicalDevanagariFromGujarati($s));
});

test('direct converter core, digits, Vedic and nukta mappings', static function (): void {
    same(toCanonicalGujaratiFromDevanagari('कृष्ण'), 'કૃષ્ણ');
    same(toCanonicalDevanagariFromGujarati('કૃષ્ણ'), 'कृष्ण');
    same(toCanonicalGujaratiFromDevanagari('१२३'), '૧૨૩');
    same(toCanonicalDevanagariFromGujarati('૧૨૩'), '१२३');
    same(toCanonicalGujaratiFromDevanagari('वसोः॑'), 'વસોઃ॑');
    same(toCanonicalDevanagariFromGujarati('વસોઃ॑'), 'वसोः॑');
    same(toCanonicalGujaratiFromDevanagari('क़ ख़ ग़ ज़ फ़'), 'ક઼ ખ઼ ગ઼ જ઼ ફ઼');
    same(toCanonicalDevanagariFromGujarati('ક઼ ખ઼ ગ઼ જ઼ ફ઼'), 'क़ ख़ ग़ ज़ फ़');
    same(toCanonicalGujaratiFromDevanagari('ॿक्ति'), 'બ઼ક્તિ');
    same(toCanonicalDevanagariFromGujarati('બ઼ક્તિ'), 'ॿक्ति');
    same(toCanonicalGujaratiFromDevanagari('ॹ'), 'ૹ');
    same(toCanonicalDevanagariFromGujarati('ૹ'), 'ॹ');
});

test('direct Devanagari → Gujarati exact metadata round-trips all 497 corpus items', static function (): void {
    $options = new IndicScriptConversionOptions(embedExactSourceMetadata: true);
    foreach (DEVANAGARI_SMOKE_SAMPLES as $index => $source) {
        $taggedGujarati = toCanonicalGujaratiFromDevanagari($source, $options);
        same(toExactDevanagariFromGujarati($taggedGujarati), $source, "direct d2g exact $index");
        same(toDevanagariFromGujarati($taggedGujarati), $source, "direct d2g smart $index");
    }
});

test('direct Gujarati → Devanagari exact metadata round-trips all 497 corpus items', static function (): void {
    $options = new IndicScriptConversionOptions(embedExactSourceMetadata: true);
    foreach (GUJARATI_SMOKE_SAMPLES as $index => $source) {
        $taggedDevanagari = toCanonicalDevanagariFromGujarati($source, $options);
        same(toExactGujaratiFromDevanagari($taggedDevanagari), $source, "direct g2d exact $index");
        same(toGujaratiFromDevanagari($taggedDevanagari), $source, "direct g2d smart $index");
    }
});

test('direct typed metadata rejects unrelated Latin-source metadata', static function (): void {
    $taggedLatin = toGujaratiFromIast('Kṛṣṇa', new IastToGujaratiOptions(embedExactSourceMetadata: true));
    throws(static fn(): string => toExactDevanagariFromGujarati($taggedLatin));
});

test('direct visible tampering invalidates exact recovery', static function (): void {
    $tagged = toCanonicalGujaratiFromDevanagari('कृष्ण', new IndicScriptConversionOptions(embedExactSourceMetadata: true));
    $tampered = str_replace('કૃષ્ણ', 'રામ', $tagged);
    throws(static fn(): string => toExactDevanagariFromGujarati($tampered));
});

echo PHP_EOL, "Result: $tests tests passed; $assertions assertions passed.", PHP_EOL;
