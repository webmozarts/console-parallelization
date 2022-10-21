<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class ThrowableCodeErrorHandler implements ErrorHandler
{
    private ErrorHandler $decoratedErrorHandler;

    public function __construct(?ErrorHandler $decoratedErrorHandler = null)
    {
        $this->decoratedErrorHandler = $decoratedErrorHandler ?? new NullErrorHandler();
    }

    public function handleError(string $item, Throwable $throwable, Logger $logger): int
    {
        $exitCode = $this->decoratedErrorHandler->handleError($item, $throwable, $logger);

        return $exitCode + max(1, $throwable->getCode());
    }
}
