<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class NullErrorHandler implements ItemProcessingErrorHandler
{
    public function handleError(string $item, Throwable $throwable, Logger $logger): void
    {
    }
}
