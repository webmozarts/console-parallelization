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

namespace Webmozarts\Console\Parallelization\ErrorHandler\Container;

use Psr\Container\ContainerInterface;
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class NonResettableContainer implements ContainerInterface
{
    public function get(string $id): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function has(string $id): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
