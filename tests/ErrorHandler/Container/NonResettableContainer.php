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

use DomainException;
use Psr\Container\ContainerInterface;

final class NonResettableContainer implements ContainerInterface
{
    public function get(string $id)
    {
        throw new DomainException('Unexpected call.');
    }

    public function has(string $id): bool
    {
        throw new DomainException('Unexpected call.');
    }
}
