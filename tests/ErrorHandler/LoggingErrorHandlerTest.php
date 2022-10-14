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

use Error;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Webmozarts\Console\Parallelization\Logger\Logger;

/**
 * @covers \Webmozarts\Console\Parallelization\ErrorHandler\LoggingErrorHandler
 */
final class LoggingErrorHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function test_it_logs_and_forwards_the_error_handling_to_the_decorated_error_handler(): void
    {
        $item = 'item1';
        $throwable = new Error('An error occurred.');

        $decoratedErrorHandlerProphecy = $this->prophesize(ErrorHandler::class);
        $decoratedErrorHandler = $decoratedErrorHandlerProphecy->reveal();

        $loggerProphecy = $this->prophesize(Logger::class);
        $logger = $loggerProphecy->reveal();

        $errorHandler = new LoggingErrorHandler($decoratedErrorHandler);

        $decoratedErrorHandlerProphecy
            ->handleError($item, $throwable, $logger)
            ->shouldBeCalledTimes(1);
        $loggerProphecy
            ->logItemProcessingFailed($item, $throwable)
            ->shouldBeCalledTimes(1);

        $errorHandler->handleError(
            $item,
            $throwable,
            $logger,
        );
    }
}
