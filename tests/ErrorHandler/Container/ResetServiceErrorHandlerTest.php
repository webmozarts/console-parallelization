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

namespace Webmozarts\Console\Parallelization\ErrorHandler\Container;

use Error;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\NullErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetServiceErrorHandler;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;

/**
 * @covers \Webmozarts\Console\Parallelization\ErrorHandler\ResetServiceErrorHandler
 *
 * @internal
 */
final class ResetServiceErrorHandlerTest extends TestCase
{
    /**
     * @dataProvider containerProvider
     */
    public function test_it_creates_an_instance_only_if_the_container_is_resettable(
        ?ContainerInterface $container,
        bool $expectInstance
    ): void {
        $errorHandler = ResetServiceErrorHandler::forContainer($container);

        if ($expectInstance) {
            self::assertInstanceOf(ResetServiceErrorHandler::class, $errorHandler);
        } else {
            self::assertInstanceOf(NullErrorHandler::class, $errorHandler);
        }
    }

    public static function containerProvider(): iterable
    {
        yield 'no container' => [
            null,
            false,
        ];

        yield 'non resettable container' => [
            new NonResettableContainer(),
            false,
        ];

        yield 'resettable container' => [
            new ResettableContainer(),
            true,
        ];

        yield 'legacy resettable container' => [
            new LegacyResettableContainer(),
            true,
        ];
    }

    public function test_it_resets_the_container_if_the_container_is_resettable(): void
    {
        $resettable = new ResettableService();

        $errorHandler = new ResetServiceErrorHandler($resettable);

        // Sanity check
        self::assertFalse($resettable->reset);

        self::handleError($errorHandler);

        self::assertTrue($resettable->reset);
    }

    private static function handleError(ErrorHandler $errorHandler): void
    {
        $errorHandler->handleError(
            'item0',
            new Error('An error occurred.'),
            new FakeLogger(),
        );
    }
}
