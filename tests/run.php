<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_latn_iast.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_deva.php';
require_once __DIR__ . '/../tools/latn_iast_transliteration_verification/example_gujr.php';

use Lipimala\AynPolicy;
use Lipimala\BrahmicLanguage;
use Lipimala\BrahmicPlainEnglishOptions;
use Lipimala\BrahmicSchwaDeletionPolicy;
use Lipimala\BrahmicVowelLengthPolicy;
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
use Lipimala\TerminalAnusvaraPolicy;
use Lipimala\TransliterationProfile;
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
use function Lipimala\toHunterianFromDevanagari;
use function Lipimala\toHunterianFromGujarati;
use function Lipimala\toHunterianFromGujaratiList;
use function Lipimala\toHunterianFromGujaratiResult;
use function Lipimala\toPlainEnglish;
use function Lipimala\toPlainEnglishFromDevanagari;
use function Lipimala\toPlainEnglishFromGujarati;
use function Lipimala\toPlainEnglishFromGujaratiList;
use function Lipimala\toPlainEnglishFromGujaratiResult;
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

test('Gujarati direct Plain-English is source-aware', static function (): void {
    $cases = [
        'આજ' => 'aaj',
        'મારે' => 'maare',
        'ઓરડે' => 'orade',
        'નટવર' => 'natvar',
        'સમાચાર' => 'samaachaar',
        'ધર્મકુમાર' => 'dharmakumaar',
        'ગુલાબના' => 'gulaabnaa',
        'હીરની' => 'heernee',
        'અમૂલ્ય' => 'amoolya',
        'ધન્ય' => 'dhanya',
        'રૂમાલ' => 'roomaal',
        'શું' => 'shun',
        'ક્યાં' => 'kyaan',
    ];
    foreach ($cases as $source => $expected) {
        same(toPlainEnglishFromGujarati($source), $expected, $source);
    }
});

test('Gujarati direct Hunterian applies Gujarati schwa rules without Hindi v-to-w leakage', static function (): void {
    $cases = [
        'નટવર' => 'natvar',
        'સમાચાર' => 'samachar',
        'ધર્મકુમાર' => 'dharmakumar',
        'સર્વે' => 'sarve',
        'શોભતા' => 'shobhta',
        'રેશમી' => 'reshmi',
        'સૂંથણલી' => 'sunthanli',
    ];
    foreach ($cases as $source => $expected) {
        same(toHunterianFromGujarati($source), $expected, $source);
    }

    same(toHunterianFromGujarati('સર્વે swan twin'), 'sarve swan twin');
    same(toHunterianFromGujarati('દુઃખ'), 'dukh');
});

test('direct Brahmic transcription preserves pre-existing Latin spans exactly', static function (): void {
    $latin = 'cat chocolate camera JavaScript café Kṛṣṇa Kr̥ṣṇa John’s ‘quote’';
    same(toPlainEnglishFromGujarati("ગુજરાત {$latin}"), "gujaraat {$latin}");
    same(toHunterianFromGujarati("ગુજરાત {$latin}"), "gujarat {$latin}");
    same(toPlainEnglishFromDevanagari("भारत {$latin}"), "bhaarat {$latin}");
    same(toHunterianFromDevanagari("भारत {$latin}"), "bharat {$latin}");
});

test('Devanagari direct transcription applies Hindi schwa deletion with Sanskrit override', static function (): void {
    same(toPlainEnglishFromDevanagari('कानपुर'), 'kaanpur');
    same(toPlainEnglishFromDevanagari('सरकार'), 'sarkaar');
    same(toPlainEnglishFromDevanagari('अहमद'), 'ahmad');
    same(toPlainEnglishFromDevanagari('धर्म'), 'dharm');
    same(toPlainEnglishFromGujarati('ધર્મ'), 'dharm');
    same(toHunterianFromDevanagari('कानपुर'), 'kanpur');
    same(toHunterianFromDevanagari('सरकार'), 'sarkar');
    same(
        toPlainEnglishFromDevanagari('कानपुर', new BrahmicPlainEnglishOptions(language: BrahmicLanguage::SANSKRIT)),
        'kaanapura',
    );
    same(
        toPlainEnglishFromDevanagari('धर्म', new BrahmicPlainEnglishOptions(language: BrahmicLanguage::SANSKRIT)),
        'dharma',
    );
});

test('Brahmic direct options control schwa, vowel length, and terminal anusvara', static function (): void {
    same(
        toPlainEnglishFromGujarati('નટવર', new BrahmicPlainEnglishOptions(schwaDeletion: BrahmicSchwaDeletionPolicy::NONE)),
        'natavara',
    );
    same(
        toPlainEnglishFromGujarati('આજ', new BrahmicPlainEnglishOptions(vowelLength: BrahmicVowelLengthPolicy::COLLAPSE)),
        'aj',
    );
    same(
        toPlainEnglishFromGujarati('શું', new BrahmicPlainEnglishOptions(terminalAnusvara: TerminalAnusvaraPolicy::DROP)),
        'shu',
    );
});

test('bulk Brahmic direct APIs are exported', static function (): void {
    same(toPlainEnglishFromGujaratiList(['આજ', 'નટવર']), ['aaj', 'natvar']);
    same(toHunterianFromGujaratiList(['સમાચાર', 'સર્વે']), ['samachar', 'sarve']);
});

test('direct result envelopes retain the exact Brahmic source', static function (): void {
    $plain = toPlainEnglishFromGujaratiResult('આજ મારે');
    same($plain->original, 'આજ મારે');
    same($plain->restoreOriginal(), 'આજ મારે');
    same($plain->rendered, 'aaj maare');
    same($plain->profile, TransliterationProfile::PLAIN_ENGLISH);

    $hunterian = toHunterianFromGujaratiResult('સમાચાર');
    same($hunterian->restoreOriginal(), 'સમાચાર');
    same($hunterian->rendered, 'samachar');
    same($hunterian->profile, TransliterationProfile::HUNTERIAN);
});

test('canonical Gujarati and legacy IAST Hunterian behavior remain unchanged', static function (): void {
    same(toCanonicalIastFromGujarati('સમાચાર'), 'samācāra');
    $hunterian = new IastPlainEnglishOptions(profile: PlainEnglishRomanizationProfile::HUNTERIAN);
    same(toPlainEnglishFromIast('Sarasvatī', $hunterian), 'Saraswati');
    same(toPlainEnglishFromIast('Rāma', $hunterian), 'Ram');
});

test('Unicode 18.0 Brahmic repertoire: rare Devanagari characters', static function (): void {
    same(toCanonicalIastFromDevanagari('ऩ'), 'ṉa');
    same(toCanonicalIastFromDevanagari('ऱ'), 'ṟa');
    same(toCanonicalIastFromDevanagari('क़'), 'qa');
    same(toCanonicalIastFromDevanagari('ख़'), 'xa');
    same(toCanonicalIastFromDevanagari('ग़'), 'ġa');
    same(toCanonicalIastFromDevanagari('ज़'), 'za');
    same(toCanonicalIastFromDevanagari('ड़'), 'ṛa');
    same(toCanonicalIastFromDevanagari('ढ़'), 'ṛha');
    same(toCanonicalIastFromDevanagari('फ़'), 'fa');
    same(toCanonicalIastFromDevanagari('य़'), 'ẏa');
    same(toCanonicalIastFromDevanagari('च़'), 'ċa');
    same(toPlainEnglishFromDevanagari('च़'), 'tsa');
    same(toHunterianFromDevanagari('च़'), 'tsa');
    same(toDevanagariFromIast('ċa'), 'च़');
    $preserve = new ScriptToIastOptions(inputNormalization: UnicodeNormalizationForm::PRESERVE);
    same(toCanonicalIastFromDevanagari('क़', $preserve), 'qa');
    same(toCanonicalIastFromDevanagari('ड़', $preserve), 'ṛa');
    same(toCanonicalIastFromDevanagari('ऀ'), "\u{0310}");
    same(toCanonicalIastFromDevanagari('॓'), "\u{0300}");
    same(toCanonicalIastFromDevanagari('॔'), "\u{0301}");
    same(toCanonicalIastFromDevanagari('ॕ'), 'ē');
    same(toPlainEnglishFromDevanagari('ऻ'), 'ooe');
    same(toPlainEnglishFromDevanagari('ॕ'), 'ee');
    same(toPlainEnglishFromDevanagari('ॴ'), 'ooe');
    same(toPlainEnglishFromDevanagari('॒॑᳚'), '');
    same(toPlainEnglishFromDevanagari('हाँ'), 'haan');
    same(toHunterianFromDevanagari('हाँ'), 'han');
    same(toPlainEnglishFromGujarati('હાઁ'), 'haan');
    same(toPlainEnglishFromDevanagari('़्'), '');
    same(toPlainEnglishFromGujarati('઼્ૺૻૼ૽૾૿'), '');
    same(toCanonicalIastFromDevanagari('कॎ'), 'ke');
    same(toCanonicalIastFromDevanagari('कॎे'), 'kai');
    same(toCanonicalIastFromDevanagari('कॎा'), 'ko');
    same(toCanonicalIastFromDevanagari('कॎो'), 'kau');
    same(toPlainEnglishFromDevanagari('कॎे'), 'kai');
    same(toHunterianFromDevanagari('कॎो'), 'kau');
    same(toCanonicalIastFromDevanagari('॰'), '॰');
    same(toCanonicalIastFromDevanagari('ॱ'), 'ॱ');
});

test('Unicode 18.0 Brahmic repertoire: Devanagari Extended vowels and sacred signs', static function (): void {
    same(toCanonicalIastFromDevanagari('ꣾ'), 'aĭ');
    same(toCanonicalIastFromDevanagari('कꣿ'), 'kaĭ');
    same(toPlainEnglishFromDevanagari('कꣿ'), 'kai');
    same(toPlainEnglishFromDevanagari('ꣾ'), 'ai');
    same(toHunterianFromDevanagari('ꣾ'), 'ai');
    same(toCanonicalIastFromDevanagari('ꣽ'), 'oṃ');
    same(toPlainEnglishFromDevanagari('ꣽ'), 'om');
    same(toPlainEnglishFromDevanagari('ॐ'), 'om');
    same(toPlainEnglishFromGujarati('ૐ'), 'om');
    same(toHunterianFromDevanagari('ॐ'), 'om');
    same(toCanonicalIastFromDevanagari('a꣼b'), 'a꣼b');
    same(toCanonicalIastFromDevanagari('a꣡'), 'a꣡');
    same(toPlainEnglishFromGujarati('a꣡'), 'a');
    same(toCanonicalIastFromDevanagari('ꣲ'), 'ṃꣲ');
    same(toCanonicalIastFromDevanagari('ꣴ'), 'ṃṃꣴ');
    same(toCanonicalIastFromDevanagari('ꣷ'), "ṃ'ꣷ");
    same(toPlainEnglishFromDevanagari('कꣲ'), 'kan');
    same(toHunterianFromDevanagari('कꣴ'), 'kann');
    same((bool) preg_match('/[ꣲ-ꣷ]/u', toPlainEnglishFromDevanagari('कꣲꣴꣷ')), false, 'special nasal signs do not leak into Plain');
    same(toCanonicalIastFromDevanagari('राम꣸'), 'rāma꣸');
    same(toPlainEnglishFromDevanagari('राम꣸'), 'raam꣸');
});

test('Unicode 18.0 Brahmic repertoire: Vedic Extensions keep their segments', static function (): void {
    same(toCanonicalIastFromDevanagari('क᳐'), 'ka᳐');
    same(toPlainEnglishFromDevanagari('क᳐'), 'ka');
    $preserveVedic = new BrahmicPlainEnglishOptions(preserveVedicAccentMarks: true);
    same(toCanonicalIastFromDevanagari('क⃰'), 'ka⃰');
    same(toPlainEnglishFromDevanagari('क⃰'), 'ka');
    same(toPlainEnglishFromDevanagari('क⃰', $preserveVedic), 'ka⃰');
    same(toPlainEnglishFromDevanagari('क॑', $preserveVedic), "ka\u{0301}");
    $visarga = toCanonicalIastFromDevanagari('देवः᳣');
    same(str_contains($visarga, 'ḥ'), true, 'visarga kept');
    same(str_contains($visarga, '᳣'), true, 'mark kept');
    same(str_contains(toPlainEnglishFromDevanagari('देवः᳣'), '᳣'), false, 'mark dropped in plain');
    same(toCanonicalIastFromDevanagari('ᳩ'), 'ṃᳩ');
    same(toCanonicalIastFromDevanagari('ᳺ'), 'ṃᳺ');
    same(toCanonicalIastFromDevanagari('ᳲ'), 'ḥᳲ');
    same(toCanonicalIastFromDevanagari('ᳵ'), 'ḫᳵ');
    same(toCanonicalIastFromDevanagari('ᳶ'), 'fᳶ');
    same(toCanonicalIastFromDevanagari('ॻ'), 'ɠa');
    same(toCanonicalIastFromDevanagari('ॼ'), 'ʄa');
    same(toCanonicalIastFromDevanagari('ॾ'), 'ɗa');
    same(toCanonicalIastFromDevanagari('ॿ'), 'ɓa');
    same(toCanonicalIastFromDevanagari('𑬊'), 'ɗa');
    same(toPlainEnglishFromDevanagari('ॻॼॾॿ'), 'gajadab');
    same(toDevanagariFromIast('ɠa'), 'ॻ');
    same(toDevanagariFromIast('ʄa'), 'ॼ');
    same(toCanonicalIastFromDevanagari('𑬀'), '𑬀');
    same(toCanonicalIastFromDevanagari('꠱'), '꠱');
    same(toCanonicalIastFromDevanagari('राम꠱'), 'rāma꠱');
    same(toPlainEnglishFromDevanagari('राम꠱'), 'raam꠱');
    same(toCanonicalIastFromDevanagari("क\u{200D}"), 'ka');
    same(toCanonicalIastFromDevanagari("क\u{200C}"), 'ka');
});

test('Unicode 18.0 Brahmic repertoire: Gujarati Arabic-transliteration marks', static function (): void {
    same(toCanonicalIastFromGujarati('કૺ'), 'k');
    same(toPlainEnglishFromGujarati('કૺ'), 'k');
    same(toHunterianFromGujarati('કૺ'), 'k');
    same(toCanonicalIastFromGujarati('અૺ'), 'a');
    same(toPlainEnglishFromGujarati('અૺ'), 'a');
    same(toCanonicalIastFromGujarati('કુૺ'), 'ku');
    same(toPlainEnglishFromGujarati('કુૺ'), 'ku');
    same(toCanonicalIastFromGujarati('નૻ'), 'nn');
    same(toPlainEnglishFromGujarati('નૻ'), 'nn');
    same(toCanonicalIastFromGujarati('કૼ'), 'kā');
    same(toPlainEnglishFromGujarati('કૼ'), 'kaa');
    same(toHunterianFromGujarati('કૼ'), 'ka');
    same(toCanonicalIastFromGujarati('કુૼ'), 'kū');
    same(toPlainEnglishFromGujarati('કુૼ'), 'koo');
    same(toHunterianFromGujarati('કુૼ'), 'ku');
    same(toCanonicalIastFromGujarati('સ૾'), 'tha');
    same(toCanonicalIastFromGujarati('ઝ૾'), 'dha');
    same(toCanonicalIastFromGujarati('ઝ૿'), 'ẓa');
    same(toCanonicalIastFromGujarati('ક૽'), 'qa');
    same(toPlainEnglishFromGujarati('સ૾'), 'tha');
    same(toHunterianFromGujarati('ઝ૿'), 'za');
    same(toCanonicalIastFromGujarati('ક૽ુ'), 'qu');
    same(toCanonicalIastFromGujarati('ક૽ૺ'), 'q');
    same(toCanonicalIastFromGujarati('સ૾ૻ'), 'thth');
    same(toCanonicalIastFromGujarati('અ૽'), 'ʿa');
    same(toCanonicalIastFromGujarati('ઇ૽'), 'ʿi');
    same(toPlainEnglishFromGujarati('અ૽'), "'a");
    same(toPlainEnglishFromGujarati('અ૽', new BrahmicPlainEnglishOptions(ayn: AynPolicy::DROP)), 'a');
    same(toPlainEnglishFromGujarati('અ૽', new BrahmicPlainEnglishOptions(ayn: AynPolicy::PRESERVE)), 'ʿa');
    same(toCanonicalIastFromGujarati('૰'), '૰');
    same(toCanonicalIastFromGujarati('૱'), '૱');
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
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $keep), $base), ['Rama', 'vrata', 'Krishna', 'Lakshmana', 'yatra']);
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $drop), $base), ['Ram', 'vrat', 'Krishn', 'Lakshman', 'yatra']);
    same(array_map(static fn(string $s): string => toPlainEnglishFromIast($s, $jna), ['jñāna', 'yajña']), ['jnan', 'yajn']);

    $hunterian = new IastPlainEnglishOptions(profile: PlainEnglishRomanizationProfile::HUNTERIAN);
    $samples = ['Rāma', 'Kṛṣṇa', 'Lakṣmaṇa', 'laṛkā', 'Rāmacandra', 'Gorakhapura', 'Sarasvatī', 'Īśvara', 'pañcāṅga', 'duḥkha', 'Devadatta', 'Jaideva', 'Kalyāṇapura', 'Nārāyaṇapura', 'Hariprasāda', 'Kṛṣṇadāsa'];
    $expected = ['Ram', 'Krishna', 'Lakshman', 'larka', 'Ramachandra', 'Gorakhapur', 'Saraswati', 'Ishwar', 'panchang', 'dukh', 'Devadatt', 'Jaidev', 'Kalyanapur', 'Narayanapur', 'Hariprasad', 'Krishnadas'];
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
