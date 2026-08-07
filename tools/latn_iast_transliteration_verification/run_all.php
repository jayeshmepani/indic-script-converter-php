<?php

declare(strict_types=1);

$files = [
    'latn_iast_to_deva_test.php',
    'latn_iast_to_gujr_test.php',
    'latn_iast_transcription_test.php',
    'deva_to_latn_iast_test.php',
    'gujr_to_latn_iast_test.php',
    'deva_to_gujr_test.php',
    'gujr_to_deva_test.php',
];

foreach ($files as $file) {
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file), $status);
    if ($status !== 0) {
        exit($status);
    }

    echo PHP_EOL;
}
