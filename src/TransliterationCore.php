<?php

declare(strict_types=1);

namespace Lipimala;

use InvalidArgumentException;
use JsonSerializable;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/Unicode.php';

enum UnicodeNormalizationForm: string
{
    case PRESERVE = 'preserve';
    case NFC = 'nfc';
    case NFD = 'nfd';
}

enum TransliterationProfile: string
{
    case STRICT_IAST = 'strictIast';
    case ISO_15919_CORE = 'iso15919Core';
    case EXTENDED_INDIC = 'extendedIndic';
    case HUNTERIAN = 'hunterian';
    case PLAIN_ENGLISH = 'plainEnglish';
}

enum TransliterationIssueSeverity: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';
}

final readonly class TransliterationIssue implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $message,
        public TransliterationIssueSeverity $severity = TransliterationIssueSeverity::WARNING,
        public ?int $sourceRuneOffset = null,
    ) {}

    /** @return array{code:string,message:string,severity:string,sourceRuneOffset:?int} */
    public function toJson(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'severity' => $this->severity->value,
            'sourceRuneOffset' => $this->sourceRuneOffset,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toJson();
    }

    /** @param array<string,mixed> $value */
    public static function fromJson(array $value): self
    {
        return new self(
            code: (string) $value['code'],
            message: (string) $value['message'],
            severity: TransliterationIssueSeverity::from((string) $value['severity']),
            sourceRuneOffset: isset($value['sourceRuneOffset']) ? (int) $value['sourceRuneOffset'] : null,
        );
    }
}

final readonly class TransliterationResult implements JsonSerializable
{
    /** @param list<TransliterationIssue> $issues */
    public function __construct(
        public string $original,
        public string $normalizedInput,
        public string $rendered,
        public TransliterationProfile $profile,
        public UnicodeNormalizationForm $inputNormalization,
        public UnicodeNormalizationForm $outputNormalization,
        public bool $renderingIsInjective,
        public array $issues = [],
    ) {}

    /** @return list<int> */
    public function originalCodePoints(): array
    {
        return Unicode::codePoints($this->original);
    }

    public function restoreOriginal(): string
    {
        return $this->original;
    }

    public function exactSourceRecoveryAvailable(): bool
    {
        return true;
    }

    public function hasErrors(): bool
    {
        foreach ($this->issues as $issue) {
            if ($issue->severity === TransliterationIssueSeverity::ERROR) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,mixed> */
    public function toJson(): array
    {
        return [
            'schema' => 'exact round-trip-indic-transliteration/1',
            'original' => $this->original,
            'originalCodePoints' => $this->originalCodePoints(),
            'normalizedInput' => $this->normalizedInput,
            'rendered' => $this->rendered,
            'profile' => $this->profile->value,
            'inputNormalization' => $this->inputNormalization->value,
            'outputNormalization' => $this->outputNormalization->value,
            'renderingIsInjective' => $this->renderingIsInjective,
            'issues' => array_map(static fn(TransliterationIssue $issue): array => $issue->toJson(), $this->issues),
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toJson();
    }

    /** @param array<string,mixed> $value */
    public static function fromJson(array $value): self
    {
        if (($value['schema'] ?? null) !== 'exact round-trip-indic-transliteration/1') {
            throw new InvalidArgumentException('Unsupported transliteration envelope.');
        }

        $original = (string) $value['original'];
        $encoded = array_map(intval(...), (array) $value['originalCodePoints']);
        $actual = Unicode::codePoints($original);
        if ($encoded !== $actual) {
            throw new InvalidArgumentException('Envelope source code-point integrity check failed.');
        }

        $issues = [];
        foreach ((array) ($value['issues'] ?? []) as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid transliteration issue payload.');
            }

            $issues[] = TransliterationIssue::fromJson($item);
        }

        return new self(
            original: $original,
            normalizedInput: (string) $value['normalizedInput'],
            rendered: (string) $value['rendered'],
            profile: TransliterationProfile::from((string) $value['profile']),
            inputNormalization: UnicodeNormalizationForm::from((string) $value['inputNormalization']),
            outputNormalization: UnicodeNormalizationForm::from((string) $value['outputNormalization']),
            renderingIsInjective: (bool) $value['renderingIsInjective'],
            issues: $issues,
        );
    }

    public function toJsonText(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        $json = json_encode($this->toJson(), $flags | JSON_THROW_ON_ERROR);
        return $json;
    }

    public static function fromJsonText(string $text): self
    {
        $value = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new InvalidArgumentException('Transliteration envelope must be a JSON object.');
        }

        return self::fromJson($value);
    }
}

final readonly class EmbeddedExactSource
{
    public function __construct(
        public string $visibleText,
        public string $originalSource,
    ) {}
}

const EXACT_SOURCE_START_TAG = 0xE0001;
const EXACT_SOURCE_END_TAG = 0xE007F;
const EXACT_SOURCE_MAGIC = 'LIT1:';

function normalizeUnicode(string $input, UnicodeNormalizationForm $form): string
{
    return Unicode::normalize($input, $form);
}

function normalize_unicode(string $input, UnicodeNormalizationForm $form): string
{
    return normalizeUnicode($input, $form);
}

function isUnicodeCombiningMark(int|string $value): bool
{
    return Unicode::isCombiningMark($value);
}

function is_unicode_combining_mark(int|string $value): bool
{
    return isUnicodeCombiningMark($value);
}

function isEncodedVedicMark(int|string $value): bool
{
    $cp = is_int($value) ? $value : Unicode::ord($value);
    return $cp === 0x0951
        || $cp === 0x0952
        || ($cp >= 0x1CD0 && $cp <= 0x1CFF)
        || ($cp >= 0xA8E0 && $cp <= 0xA8FF);
}

function is_encoded_vedic_mark(int|string $value): bool
{
    return isEncodedVedicMark($value);
}

function fnv1a32(string $bytes): int
{
    $hash = 0x811C9DC5;
    $length = strlen($bytes);
    for ($i = 0; $i < $length; ++$i) {
        $hash ^= ord($bytes[$i]);
        $hash = ($hash * 0x01000193) & 0xFFFFFFFF;
    }

    return $hash;
}

function checksumHex(string $bytes): string
{
    return str_pad(strtolower(dechex(fnv1a32($bytes))), 8, '0', STR_PAD_LEFT);
}

function base64UrlEncode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function base64UrlDecode(string $encoded): string
{
    $padded = str_pad($encoded, strlen($encoded) + ((4 - strlen($encoded) % 4) % 4), '=', STR_PAD_RIGHT);
    $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
    if ($decoded === false) {
        throw new InvalidArgumentException('Invalid base64url payload.');
    }

    return $decoded;
}

function embedExactSourceMetadata(string $rendered, string $originalSource): string
{
    $bytes = Unicode::toUtf16Le($originalSource);
    $encoded = base64UrlEncode($bytes);
    $sourceChecksum = checksumHex($bytes);
    $renderedChecksum = checksumHex(Unicode::toUtf16Le($rendered));
    $payload = EXACT_SOURCE_MAGIC . $encoded . ':' . $sourceChecksum . ':' . $renderedChecksum;

    $tagged = [EXACT_SOURCE_START_TAG];
    foreach (Unicode::codePoints($payload) as $unit) {
        if ($unit < 0x20 || $unit > 0x7E) {
            throw new RuntimeException('Exact-source payload unexpectedly contains non-ASCII.');
        }

        $tagged[] = 0xE0000 + $unit;
    }

    $tagged[] = EXACT_SOURCE_END_TAG;

    return $rendered . Unicode::fromCodePoints($tagged);
}

function embed_exact_source_metadata(string $rendered, string $originalSource): string
{
    return embedExactSourceMetadata($rendered, $originalSource);
}

function tryDecodeExactSourceMetadata(string $text): ?EmbeddedExactSource
{
    try {
        $runes = Unicode::codePoints($text);
    } catch (Throwable) {
        return null;
    }

    if ($runes === [] || $runes[array_key_last($runes)] !== EXACT_SOURCE_END_TAG) {
        return null;
    }

    $start = count($runes) - 2;
    while ($start >= 0 && $runes[$start] !== EXACT_SOURCE_START_TAG) {
        --$start;
    }

    if ($start < 0) {
        return null;
    }

    $payloadUnits = [];
    for ($i = $start + 1, $end = count($runes) - 1; $i < $end; ++$i) {
        $rune = $runes[$i];
        if ($rune < 0xE0020 || $rune > 0xE007E) {
            return null;
        }

        $payloadUnits[] = $rune - 0xE0000;
    }

    $payload = Unicode::fromCodePoints($payloadUnits);
    if (!str_starts_with($payload, EXACT_SOURCE_MAGIC)) {
        return null;
    }

    $body = substr($payload, strlen(EXACT_SOURCE_MAGIC));
    $renderedSplit = strrpos($body, ':');
    if ($renderedSplit === false || $renderedSplit <= 0 || $renderedSplit === strlen($body) - 1) {
        return null;
    }

    $prefix = substr($body, 0, $renderedSplit);
    $sourceSplit = strrpos($prefix, ':');
    if ($sourceSplit === false || $sourceSplit <= 0 || $sourceSplit === $renderedSplit - 1) {
        return null;
    }

    $encoded = substr($body, 0, $sourceSplit);
    $sourceChecksumText = substr($body, $sourceSplit + 1, $renderedSplit - $sourceSplit - 1);
    $renderedChecksumText = substr($body, $renderedSplit + 1);
    if (preg_match('/^[0-9a-f]{8}$/D', $sourceChecksumText) !== 1 || preg_match('/^[0-9a-f]{8}$/D', $renderedChecksumText) !== 1) {
        return null;
    }

    try {
        $bytes = base64UrlDecode($encoded);
        if (checksumHex($bytes) !== $sourceChecksumText) {
            return null;
        }

        $source = Unicode::fromUtf16Le($bytes);
        $visible = Unicode::fromCodePoints(array_slice($runes, 0, $start));
        if (checksumHex(Unicode::toUtf16Le($visible)) !== $renderedChecksumText) {
            return null;
        }

        return new EmbeddedExactSource($visible, $source);
    } catch (Throwable) {
        return null;
    }
}

function try_decode_exact_source_metadata(string $text): ?EmbeddedExactSource
{
    return tryDecodeExactSourceMetadata($text);
}

function stripExactSourceMetadata(string $text): string
{
    return tryDecodeExactSourceMetadata($text)?->visibleText ?? $text;
}

function strip_exact_source_metadata(string $text): string
{
    return stripExactSourceMetadata($text);
}

function recoverEmbeddedExactSource(string $text): ?string
{
    return tryDecodeExactSourceMetadata($text)?->originalSource;
}

function recover_embedded_exact_source(string $text): ?string
{
    return recoverEmbeddedExactSource($text);
}

function hasEmbeddedExactSource(string $text): bool
{
    return tryDecodeExactSourceMetadata($text) instanceof EmbeddedExactSource;
}

function has_embedded_exact_source(string $text): bool
{
    return hasEmbeddedExactSource($text);
}
