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
use Webmozarts\Console\Parallelization\Logger\FakeLogger;

/**
 * @covers \Webmozarts\Console\Parallelization\ErrorHandler\ResetContainerErrorHandler
 */
final class ResetContainerErrorhandlerTest extends TestCase
{
    public function test_it_does_nothing_if_the_container_is_not_resettable(): void
    {
        $errorHandler = new ResetContainerErrorHandler(
            new NonResettableContainer(),
        );

        self::handleError($errorHandler);

        $this->addToAssertionCount(1);
    }

    public function test_it_resets_the_container_if_the_container_is_resettable(): void
    {
        $container = new ResettableContainer();

        $errorHandler = new ResetContainerErrorHandler($container);

        // Sanity check
        self::assertFalse($container->reset);

        self::handleError($errorHandler);

        self::assertTrue($container->reset);
    }

    private static function handleError(ItemProcessingErrorHandler $errorHandler): void
    {
        $errorHandler->handleError(
            'item0',
            new Error('An error occurred.'),
            new FakeLogger(),
        );
    }
}
