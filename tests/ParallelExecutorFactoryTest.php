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

namespace Webmozarts\Console\Parallelization;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Webmozarts\Console\Parallelization\ErrorHandler\FakeErrorHandler;
use Webmozarts\Console\Parallelization\Process\FakeProcessLauncherFactory;

/**
 * @covers \Webmozarts\Console\Parallelization\ParallelExecutorFactory
 */
final class ParallelExecutorFactoryTest extends TestCase
{
    private const FILE_1 = __DIR__;
    private const FILE_2 = __DIR__.'/Logger/FakeLogger.php';
    private const FILE_3 = __DIR__.'/ErrorHandler/FakeErrorHandler.php';

    public function test_it_can_create_a_configured_executor(): void
    {
        $commandName = 'import:items';
        $definition = new InputDefinition();
        $errorHandler = new FakeErrorHandler();

        $callable0 = self::createCallable(0);
        $callable1 = self::createCallable(1);
        $callable2 = self::createCallable(2);
        $callable3 = self::createCallable(3);
        $callable4 = self::createCallable(4);
        $callable5 = self::createCallable(5);
        $callable6 = self::createCallable(6);

        $batchSize = 10;
        $segmentSize = 20;
        $extraEnvironmentVariables = ['CUSTOM_CI' => '0'];
        $progressSymbol = 'ø';
        $processLauncherFactory = new FakeProcessLauncherFactory();

        $executor = ParallelExecutorFactory::create(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
        )
            ->withBatchSize($batchSize)
            ->withSegmentSize($segmentSize)
            ->withRunBeforeFirstCommand($callable3)
            ->withRunAfterLastCommand($callable4)
            ->withRunBeforeBatch($callable5)
            ->withRunAfterBatch($callable6)
            ->withProgressSymbol($progressSymbol)
            ->withPhpExecutable(self::FILE_1)
            ->withScriptPath(self::FILE_2)
            ->withWorkingDirectory(self::FILE_3)
            ->withExtraEnvironmentVariables($extraEnvironmentVariables)
            ->withProcessLauncherFactory($processLauncherFactory)
            ->build();

        $expected = new ParallelExecutor(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
            $batchSize,
            $segmentSize,
            $callable3,
            $callable4,
            $callable5,
            $callable6,
            $progressSymbol,
            self::FILE_1,
            self::FILE_2,
            self::FILE_3,
            $extraEnvironmentVariables,
            $processLauncherFactory,
        );

        self::assertEquals($expected, $executor);
    }

    private static function createCallable(int $id): callable
    {
        return static function () use ($id): void {
            echo $id;
        };
    }
}
