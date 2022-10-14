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

use Throwable;

final class LoggingErrorHandler implements ErrorHandler
{
    private ErrorHandler $decoratedErrorHandler;

    public function __construct(?ErrorHandler $decoratedErrorHandler = null)
    {
        $this->decoratedErrorHandler = $decoratedErrorHandler ?? new NullErrorHandler();
    }

    public function handleError(string $item, Throwable $throwable, $logger): void
    {
        $logger->logItemProcessingFailed($item, $throwable);

        $this->decoratedErrorHandler->handleError($item, $throwable, $logger);
    }
}
