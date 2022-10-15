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

namespace Webmozarts\Console\Parallelization;

use Symfony\Component\HttpKernel\Kernel;

final class SymfonyVersion
{
    private function __construct()
    {
    }

    public static function isSymfony4(): bool
    {
        // @phpstan-ignore-next-line
        return Kernel::MAJOR_VERSION === 4;
    }
}
