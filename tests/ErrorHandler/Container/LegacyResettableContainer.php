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

namespace Symfony\Component\DependencyInjection;

use Psr\Container\ContainerInterface as PsrContainerInterface;
use function interface_exists;

if (!interface_exists(ResettableContainerInterface::class)) {
    interface ResettableContainerInterface extends PsrContainerInterface
    {
        public function reset();
    }
}

namespace Webmozarts\Console\Parallelization\ErrorHandler\Container;

use DomainException;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;

final class LegacyResettableContainer implements ResettableContainerInterface
{
    public bool $called = false;

    public function reset(): void
    {
        $this->called = true;
    }

    public function get($id, $invalidBehavior = 1): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function has(string $id): void
    {
        throw new DomainException('Unexpected call.');
    }
}
