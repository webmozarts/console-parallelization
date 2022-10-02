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

namespace Webmozarts\Console\Parallelization\Process;

use function class_alias;
use const PHP_VERSION_ID;

$sourceClass = PHP_VERSION_ID > 80_100
    ? DummyProcess81::class
    : DummyProcess74::class;

class_alias($sourceClass, \Webmozarts\Console\Parallelization\Process\DummyProcess::class);
