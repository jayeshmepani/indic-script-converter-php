<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\EarlyReturn\Rector\Foreach_\ChangeNestedForeachIfsToEarlyContinueRector;
use Rector\EarlyReturn\Rector\If_\ChangeOrIfContinueToMultiContinueRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/bin',
        __DIR__ . '/examples',
        __DIR__ . '/tools',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/node_modules',
        __DIR__ . '/storage',
        __DIR__ . '/docs',
        __DIR__ . '/misc',
        __DIR__ . '/coverage',
        __DIR__ . '/build',
        __DIR__ . '/bootstrap/cache',
        __DIR__ . '/tests/Fixtures',
        __DIR__ . '/tests/fixtures',
    ])
    ->withPhpSets()
    ->withComposerBased(
        laravel: true,
        phpunit: true,
    )
    ->withPreparedSets(
        deadCode: false,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: false,
        naming: false,
        instanceOf: false,
        earlyReturn: true,
    )
    ->withSkip([
        ChangeOrIfContinueToMultiContinueRector::class,
        ChangeNestedForeachIfsToEarlyContinueRector::class,
    ])
    ->withParallel()
    ->withImportNames(
        importNames: true,
        removeUnusedImports: true,
    );
