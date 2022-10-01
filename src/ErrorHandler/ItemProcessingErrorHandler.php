<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Throwable;

interface ItemProcessingErrorHandler
{
    public function handleError(string $item, Throwable $throwable): void;
}
