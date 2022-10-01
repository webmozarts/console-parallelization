<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Webmozarts\Console\Parallelization\Symfony\ResettableContainerInterface;
use function class_exists;

final class ResetContainerErrorhandler implements ItemProcessingErrorHandler
{
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
                class_exists(ResetInterface::class)
                && $container instanceof ResetInterface
            )
            // TODO: to remove once we drop Symfony 4.4 support.
            || (class_exists(ResettableContainerInterface::class)
                && $container instanceof ResettableContainerInterface
            );
    }
}
