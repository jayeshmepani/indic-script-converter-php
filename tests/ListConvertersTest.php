<?php

declare(strict_types=1);

namespace Lipimala\Tests;

use Lipimala\DigitPolicy;
use Lipimala\IastPlainEnglishOptions;
use Lipimala\IastToDevanagariOptions;
use Lipimala\PlainEnglishFinalAPolicy;
use PHPUnit\Framework\TestCase;

use function Lipimala\toCanonicalDevanagariFromGujaratiList;
use function Lipimala\toCanonicalGujaratiFromDevanagariList;
use function Lipimala\toCanonicalIastFromDevanagariList;
use function Lipimala\toCanonicalIastFromGujaratiList;
use function Lipimala\toDevanagariFromIastList;
use function Lipimala\toDevanagariList;
use function Lipimala\toGujaratiFromIastList;
use function Lipimala\toGujaratiList;
use function Lipimala\toPlainEnglishFromIastList;
use function Lipimala\toPlainEnglishList;

final class ListConvertersTest extends TestCase
{
    public function testBulkListConvertersAllDirections(): void
    {
        $iastItems = ['Kṛṣṇa', 'Rāma', 'jñāna'];

        // 1. Latin (IAST) → Devanagari / Gujarati / Plain English
        $devaList = toDevanagariFromIastList($iastItems);
        self::assertSame(['कृष्ण', 'राम', 'ज्ञान'], $devaList);

        $gujrList = toGujaratiFromIastList($iastItems);
        self::assertSame(['કૃષ્ણ', 'રામ', 'જ્ઞાન'], $gujrList);

        $plainList = toPlainEnglishFromIastList($iastItems);
        self::assertSame(['Krishna', 'Ram', 'gyan'], $plainList);

        // 2. Brahmic → Latin IAST
        $iastFromDeva = toCanonicalIastFromDevanagariList($devaList);
        self::assertSame(['kṛṣṇa', 'rāma', 'jñāna'], $iastFromDeva);

        $iastFromGujr = toCanonicalIastFromGujaratiList($gujrList);
        self::assertSame(['kṛṣṇa', 'rāma', 'jñāna'], $iastFromGujr);

        // 3. Direct Devanagari ↔ Gujarati
        $gujrDirect = toCanonicalGujaratiFromDevanagariList($devaList);
        self::assertSame(['કૃષ્ણ', 'રામ', 'જ્ઞાન'], $gujrDirect);

        $devaDirect = toCanonicalDevanagariFromGujaratiList($gujrList);
        self::assertSame(['कृष्ण', 'राम', 'ज्ञान'], $devaDirect);

        // 4. Result Envelopes
        $envDeva = toDevanagariList($iastItems);
        self::assertCount(3, $envDeva);
        self::assertSame('कृष्ण', $envDeva[0]->rendered);
        self::assertSame('राम', $envDeva[1]->rendered);

        $envGujr = toGujaratiList($iastItems);
        self::assertCount(3, $envGujr);
        self::assertSame('કૃષ્ણ', $envGujr[0]->rendered);

        $envPlain = toPlainEnglishList($iastItems);
        self::assertCount(3, $envPlain);
        self::assertSame('Krishna', $envPlain[0]->rendered);
    }

    public function testBulkListConvertersCustomOptions(): void
    {
        $items = ['Rāma 123', 'jñāna'];

        $devaDigits = toDevanagariFromIastList(
            $items,
            new IastToDevanagariOptions(digitPolicy: DigitPolicy::ConvertToScript)
        );
        self::assertSame(['राम १२३', 'ज्ञान'], $devaDigits);

        $plainKeepFinalA = toPlainEnglishFromIastList(
            $items,
            new IastPlainEnglishOptions(finalAPolicy: PlainEnglishFinalAPolicy::Keep)
        );
        self::assertSame(['Rama 123', 'gyana'], $plainKeepFinalA);
    }
}
