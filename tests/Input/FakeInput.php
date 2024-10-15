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

namespace Webmozarts\Console\Parallelization\Input;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use function class_alias;

$isSfConsole7OrHigher = InstalledVersions::satisfies(
    new VersionParser(),
    'symfony/console',
    '^7.0',
);

class_alias(
    $isSfConsole7OrHigher
        ? FakeSymfony7Input::class
        : FakeSymfony6Input::class,
    FakeInput::class,
);
