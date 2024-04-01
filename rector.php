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
use Rector\Php71\Rector\FuncCall\CountOnNullRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/.php-cs-fixer.php',
        __DIR__.'/rector.php',
        __DIR__.'/src',
        __DIR__.'/tests',
    ]);

    $rectorConfig->importNames();

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
    ]);

    $rectorConfig->skip([
        CountOnNullRector::class,
        NullToStrictStringFuncCallArgRector::class => [
            __DIR__.'/src/ParallelExecutorFactory.php',
            __DIR__.'/tests/Integration/OutputNormalizer.php',
        ],

        __DIR__.'/tests/Integration/var',
    ]);
};
