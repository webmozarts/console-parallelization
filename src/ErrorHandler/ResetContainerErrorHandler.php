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

use function interface_exists;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Webmozarts\Console\Parallelization\Symfony\ResettableContainerInterface;

final class ResetContainerErrorHandler implements ItemProcessingErrorHandler
{
    /**
     * @var (ContainerInterface&ResetInterface)|null
     */
    private ?ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = self::isResettable($container) ? $container : null;
    }

    public function handleError(string $item, Throwable $throwable): void
    {
        if (null !== $this->container) {
            $this->container->reset();
        }
    }

    private static function isResettable(ContainerInterface $container): bool
    {
        return (
            interface_exists(ResetInterface::class)
            && $container instanceof ResetInterface
        )
            // TODO: to remove once we drop Symfony 4.4 support.
            || (interface_exists(ResettableContainerInterface::class)
            && $container instanceof ResettableContainerInterface
            );
    }
}
