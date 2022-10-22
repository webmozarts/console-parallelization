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
use Exception;
use PHPUnit\Framework\TestCase;
use Throwable;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;

/**
 * @covers \Webmozarts\Console\Parallelization\ErrorHandler\ThrowableCodeErrorHandler
 *
 * @internal
 */
final class ThrowableCodeErrorHandlerTest extends TestCase
{
    /**
     * @dataProvider throwableProvider
     */
    public function test_it_returns_the_code_of_the_throwable(
        Throwable $throwable,
        int $expectedExitCode
    ): void {
        $errorHandler = new ThrowableCodeErrorHandler();

        $exitCode = $errorHandler->handleError(
            'item0',
            $throwable,
            new FakeLogger(),
        );

        self::assertSame($expectedExitCode, $exitCode);
    }

    public static function throwableProvider(): iterable
    {
        yield 'exception' => [
            new Exception('', 7),
            7,
        ];

        yield 'error' => [
            new Error('', 7),
            7,
        ];

        yield '0 code' => [
            new Error('', 0),
            1,
        ];
    }

    public function test_it_returns_the_code_of_the_throwable_and_of_the_decorated_handler_when_there_is_one(): void
    {
        $errorHandler = new ThrowableCodeErrorHandler(
            new DummyErrorHandler(2),
        );

        $exitCode = $errorHandler->handleError(
            'item0',
            new Error('smth happened', 7),
            new FakeLogger(),
        );

        self::assertSame(9, $exitCode);
    }
}
