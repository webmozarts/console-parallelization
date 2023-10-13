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
use Webmozarts\Console\Parallelization\Logger\Logger;

final class LoggingErrorHandler implements ErrorHandler
{
    public function __construct(
        private readonly ErrorHandler $decoratedErrorHandler = new NullErrorHandler(),
    ) {
    }

    public function handleError(string $item, Throwable $throwable, Logger $logger): int
    {
        $logger->logItemProcessingFailed($item, $throwable);

        return $this->decoratedErrorHandler->handleError($item, $throwable, $logger);
    }
}
