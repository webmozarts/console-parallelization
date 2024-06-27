<?php

/*
 * This file is part of the Webmozarts Console Parallelization package.
 *
 * (c) Webmozarts GmbH <office@webmozarts.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/.php-cs-fixer.php',
        __DIR__.'/rector.php',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->withSkipPath(__DIR__.'/tests/Integration/var')
    ->withImportNames(
        removeUnusedImports: true,
    )
    ->withPhpSets(php81: true)
    ->withAttributesSets(
        phpunit: true,
    )
    ->withSkip([
        NullToStrictStringFuncCallArgRector::class => [
            __DIR__.'/src/ParallelExecutorFactory.php',
            __DIR__.'/tests/Integration/OutputNormalizer.php',
        ],

        __DIR__.'/tests/Integration/var',
    ]);
