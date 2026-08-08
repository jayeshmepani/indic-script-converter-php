<?php

declare(strict_types=1);

namespace Lipimala\Tests;

use PHPUnit\Framework\TestCase;

use function Lipimala\toCanonicalDevanagariFromGujaratiList;
use function Lipimala\toCanonicalGujaratiFromDevanagariList;
use function Lipimala\toDevanagariFromIastList;
use function Lipimala\toDevanagariList;
use function Lipimala\toGujaratiFromIastList;
use function Lipimala\toPlainEnglishFromIastList;

final class ListConvertersTest extends TestCase
{
    public function testBulkListConverters(): void
    {
        $items = ['Kṛṣṇa', 'Rāma', 'jñāna'];

        $devaList = toDevanagariFromIastList($items);
        self::assertSame(['कृष्ण', 'राम', 'ज्ञान'], $devaList);

        $gujrList = toGujaratiFromIastList($items);
        self::assertSame(['કૃષ્ણ', 'રામ', 'જ્ઞાન'], $gujrList);

        $plainList = toPlainEnglishFromIastList($items);
        self::assertSame(['Krishna', 'Ram', 'gyan'], $plainList);

        $gujrDirect = toCanonicalGujaratiFromDevanagariList($devaList);
        self::assertSame(['કૃષ્ણ', 'રામ', 'જ્ઞાન'], $gujrDirect);

        $devaDirect = toCanonicalDevanagariFromGujaratiList($gujrList);
        self::assertSame(['कृष्ण', 'राम', 'ज्ञान'], $devaDirect);

        $envList = toDevanagariList($items);
        self::assertCount(3, $envList);
        self::assertSame('कृष्ण', $envList[0]->rendered);
        self::assertSame('राम', $envList[1]->rendered);
    }
}
