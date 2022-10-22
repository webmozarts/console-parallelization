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
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;

/**
 * @covers \Webmozarts\Console\Parallelization\ErrorHandler\LoggingErrorHandler
 *
 * @internal
 */
final class LoggingErrorHandlerTest extends TestCase
{
    use ProphecyTrait;

    public function test_it_logs_and_forwards_the_error_handling_to_the_decorated_error_handler(): void
    {
        $item = 'item1';
        $throwable = new Error('An error occurred.');
        $logger = new DummyLogger();

        $expectedExitCode = 10;
        $decoratedErrorHandler = new DummyErrorHandler($expectedExitCode);

        $errorHandler = new LoggingErrorHandler($decoratedErrorHandler);

        $actualExitCode = $errorHandler->handleError(
            $item,
            $throwable,
            $logger,
        );

        self::assertSame($expectedExitCode, $actualExitCode);
        self::assertSame(
            [[$item, $throwable, $logger]],
            $decoratedErrorHandler->calls,
        );
        self::assertSame(
            [['logItemProcessingFailed', [$item, $throwable]]],
            $logger->records,
        );
    }

    public function test_it_can_be_created_and_called_without_a_decorated_handler(): void
    {
        $item = 'item1';
        $throwable = new Error('An error occurred.');

        $loggerProphecy = $this->prophesize(Logger::class);
        $logger = $loggerProphecy->reveal();

        $errorHandler = new LoggingErrorHandler();

        $loggerProphecy
            ->logItemProcessingFailed($item, $throwable)
            ->shouldBeCalledTimes(1);

        $exitCode = $errorHandler->handleError(
            $item,
            $throwable,
            $logger,
        );

        self::assertSame($exitCode, 0);
    }
}
