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

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class ResetServiceErrorHandler implements ErrorHandler
{
    public function __construct(
        private readonly ResetInterface $resettable,
        private readonly ErrorHandler $decoratedErrorHandler = new NullErrorHandler(),
    ) {
    }

    public static function forContainer(?ContainerInterface $container): ErrorHandler
    {
        return $container instanceof ResetInterface
            ? new self($container)
            : new NullErrorHandler();
    }

    public function handleError(string $item, Throwable $throwable, Logger $logger): int
    {
        $this->resettable->reset();

        return $this->decoratedErrorHandler->handleError($item, $throwable, $logger);
    }
}
