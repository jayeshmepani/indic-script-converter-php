#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use Lipimala\BrahmicPlainEnglishOptions;
use Lipimala\BrahmicReadableStyle;
use Lipimala\BrahmicVowelLengthPolicy;

use function Lipimala\toHunterianFromGujarati;
use function Lipimala\toPlainEnglishFromGujarati;

$lines = [
    'પછી તે ધર્મ જેટલાં બ્રહ્માંડ તેટલા સ્વરૂપ ધારણ કરીને દરેક બ્રહ્માંડમાં ગયા. તે શ્વેતદ્વીપ પાસે વિશ્વકર્મા દ્વારા વિષ્ણુપુર રચાવીને તેમાં રહે છે. ધર્મ એ વિષ્ણુપુરમાંથી મૂર્તિ સહિત સાબરમતીને કાંઠે (નારાયણઘાટે) આવીને દશ વર્ષ તપ કર્યું. તે સમયે વારાહ કલ્પ, સ્વયંભૂ, મન્વંતર, પ્રથમનો સતયુગ, પ્રભવ વર્ષ, ઉત્તરાયન કાળ, વસંત ઋતુ, ચૈત્ર માસ, શુકલપક્ષ, એકાદશી, બ્રાહ્મમુહૂર્ત અને રવિવારને દિવસે નર,નારાયણ, હરિ, કૃષ્ણ એ ચાર સ્વરૂપે પ્રગટ થાતા હવા.ચાક્ષુષ મન્વંતરમાં દુર્વાસાના શાપથી પ્રલય થયો. ત્યારે નરનારાયણ સ્વધામ-બ્રહ્મધામ પધાર્યા અને ધર્મ - મૂર્તિ અક્ષરધામમાં પધાર્યાં.',
        'પદ - ૧',
    'આજ મારે ઓરડે રે, આવ્યા અવિનાશી અલબેલ;',
    'બાઈ મેં બોલાવિયા રે, સુંદર છોગાવાળો છેલ... ૧',
    'નીરખ્યા નેણાં ભરી રે, નટવર સુંદર શ્રી ઘનશ્યામ;',
    'શોભા શી કહું રે, નીરખી લાજે કોટિક કામ... ૨',
    'ગૂંથી ગુલાબના રે, કંઠે આરોપ્યા મેં હાર;',
    'લઈને વારણાં રે, ચરણે લાગી વારંવાર... ૩',
    'આપ્યો મેં તો આદરે રે, બેસવા ચાકળિયો કરી પ્યાર;',
    'પૂછ્યા પ્રીતશું રે, બાઈ મેં સર્વે સમાચાર... ૪',
    'કહોને હરિ ક્યાં હતા રે, ક્યાં થકી આવ્યા ધર્મકુમાર;',
    'સુંદર શોભતા રે, અંગે સજિયા છે શણગાર... ૫',
    'પહેરી પ્રીત શું રે, સુરંગી સૂંથણલી સુખદેણ;',
    'નાડી હીરની રે, જોતાં તૃપ્ત ન થાયે નેણ... ૬',
    'ઉપર ઓઢિયો રે, ગૂઢો રેંટો જોયા લાગ;',
    'સજની તે સમે રે, ધન્ય ધન્ય નીરખ્યા તેનાં ભાગ્ય... ૭',
    'મસ્તક ઉપરે રે, બાંધ્યું મોળીડું અમૂલ્ય;',
    'કોટિક રવિ શશી રે, તે તો નાવે તેને તુલ્ય... ૮',
    'રેશમી કોરનો રે, કરમાં સાહ્યો છે રૂમાલ;',
    'પ્રેમાનંદ તો રે, એ છબી નીરખી થયો નિહાલ... ૯',
];

$options = new BrahmicPlainEnglishOptions(
    vowelLength: BrahmicVowelLengthPolicy::SMART,
    readableStyle: BrahmicReadableStyle::NATURAL,
);

foreach ($lines as $line) {
    echo "GUJ : {$line}\n";
    echo 'PLAIN: ' . toPlainEnglishFromGujarati($line, $options) . "\n";
    echo 'HUNT : ' . toHunterianFromGujarati($line, $options) . "\n\n";
}

foreach ($lines as $line) {
    echo "GUJ : {$line}\n";
    echo 'PLAIN w/o opt: ' . toPlainEnglishFromGujarati($line) . "\n";
    echo 'HUNT w/o opt: ' . toHunterianFromGujarati($line) . "\n\n";
}