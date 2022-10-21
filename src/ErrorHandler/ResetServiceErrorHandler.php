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
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;
use function interface_exists;

final class ResetServiceErrorHandler implements ErrorHandler
{
    /**
     * @var ResetInterface|ResettableContainerInterface
     */
    private $resettable;
    private ErrorHandler $decoratedErrorHandler;

    /**
     * @param ResetInterface|ResettableContainerInterface $resettable
     */
    public function __construct(
        $resettable,
        ?ErrorHandler $decoratedErrorHandler = null
    ) {
        $this->resettable = $resettable;
        $this->decoratedErrorHandler = $decoratedErrorHandler ?? new NullErrorHandler();
    }

    public static function forContainer(?ContainerInterface $container): ErrorHandler
    {
        return null !== $container && self::isResettable($container)
            ? new self($container)
            : new NullErrorHandler();
    }

    public function handleError(string $item, Throwable $throwable, Logger $logger): void
    {
        $this->resettable->reset();

        return $this->decoratedErrorHandler->handleError($item, $throwable, $logger);
    }

    private static function isResettable(ContainerInterface $container): bool
    {
        return (
            interface_exists(ResetInterface::class)
            && $container instanceof ResetInterface
        )
            // TODO: to remove once we drop Symfony 4.4 support.
            || (
                interface_exists(ResettableContainerInterface::class)
            && $container instanceof ResettableContainerInterface
            );
    }
}
