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

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use DomainException;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;

final class ResettableContainer implements ContainerInterface, ResetInterface
{
    public bool $reset = false;

    public function get(string $id)
    {
        throw new DomainException('Unexpected call.');
    }

    public function has(string $id): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function reset(): void
    {
        $this->reset = true;
    }
}
