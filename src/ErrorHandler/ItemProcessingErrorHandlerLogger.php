<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\ErrorHandler;

use Throwable;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class ItemProcessingErrorHandlerLogger implements ItemProcessingErrorHandler
{
    private ItemProcessingErrorHandler $decoratedErrorHandler;
    private Logger $logger;

    public function __construct(
        ItemProcessingErrorHandler $decoratedErrorHandler,
        Logger $logger
    ) {
        $this->decoratedErrorHandler = $decoratedErrorHandler;
        $this->logger = $logger;
    }

    public function handleError(string $item, Throwable $throwable): void
    {
        $this->logger->processingItemFailed($item, $throwable);

        $this->decoratedErrorHandler->handleError($item, $throwable);
    }
}
