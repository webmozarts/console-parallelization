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

use function array_key_exists;
use function array_keys;
use function array_map;
use function chr;
use function getcwd;
use PHPUnit\Framework\TestCase;
use function Safe\chdir;
use function Safe\putenv;
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

        $childSourceStream = StringStream::fromString('');
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
            ->withChildSourceStream($childSourceStream)
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
            $childSourceStream,
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

    /**
     * @dataProvider defaultValuesProvider
     */
    public function test_it_can_create_an_executor_with_default_values(
        array $environmentVariables,
        string $workingDirectory,
        string $expectedSymbol,
        string $expectedPhpExecutable,
        string $expectedScriptPath,
        string $expectedWorkingDirectory
    ): void {
        $cleanUpWorkingDirectory = self::moveToWorkingDirectory($workingDirectory);
        $cleanUpEnvironmentVariables = self::setEnvironmentVariables($environmentVariables);

        $expected = ParallelExecutorFactory::create(
            static fn () => ['item1', 'item2'],
            static fn () => '',
            static fn () => 'item',
            'import:movies',
            new InputDefinition(),
            new FakeErrorHandler(),
        )
            ->withProgressSymbol($expectedSymbol)
            ->withPhpExecutable($expectedPhpExecutable)
            ->withScriptPath($expectedScriptPath)
            ->withWorkingDirectory($expectedWorkingDirectory)
            ->build();

        $actual = ParallelExecutorFactory::create(
            static fn () => ['item1', 'item2'],
            static fn () => '',
            static fn () => 'item',
            'import:movies',
            new InputDefinition(),
            new FakeErrorHandler(),
        )->build();

        $cleanUpWorkingDirectory();
        $cleanUpEnvironmentVariables();

        self::assertEquals($expected, $actual);
    }

    public static function defaultValuesProvider(): iterable
    {
        $progressSymbol = chr(254);
        $phpExecutable = __DIR__.'/Fixtures/fake-php-executable.php';
        $expectedScriptPath = __DIR__.'/../bin/console';
        $workingDirectory = __DIR__.'/Process';

        yield 'nominal' => [
            [
                'PHP_BINARY' => $phpExecutable,
                'PWD' => __DIR__.'/..',
                'SCRIPT_NAME' => 'bin/console',
            ],
            $workingDirectory,
            $progressSymbol,
            $phpExecutable,
            $expectedScriptPath,
            $workingDirectory,
        ];

        yield 'script name is an absolute path' => [
            [
                'PHP_BINARY' => $phpExecutable,
                'PWD' => __DIR__.'/..',
                'SCRIPT_NAME' => __DIR__.'/../bin/console',
            ],
            $workingDirectory,
            $progressSymbol,
            $phpExecutable,
            $expectedScriptPath,
            $workingDirectory,
        ];
    }

    /**
     * @return callable():void
     */
    private static function moveToWorkingDirectory(string $workingDirectory): callable
    {
        $currentWorkingDirectory = getcwd();
        chdir($workingDirectory);

        return static fn () => chdir($currentWorkingDirectory);
    }

    /**
     * @param array<string, string> $environmentVariables
     *
     * @return callable():void
     */
    private static function setEnvironmentVariables(array $environmentVariables): callable
    {
        $restoreEnvironmentVariables = array_map(
            static fn (string $name) => self::setEnvironmentVariable($name, $environmentVariables[$name]),
            array_keys($environmentVariables),
        );

        return static function () use ($restoreEnvironmentVariables): void {
            foreach ($restoreEnvironmentVariables as $restoreEnvironmentVariable) {
                $restoreEnvironmentVariable();
            }
        };
    }

    /**
     * @return callable():void
     */
    private static function setEnvironmentVariable(string $name, string $value): callable
    {
        if (array_key_exists($name, $_SERVER)) {
            $previousValue = $_SERVER[$name];

            $restoreServer = static fn () => $_SERVER[$name] = $previousValue;
        } else {
            $restoreServer = static function () use ($name) {
                unset($_SERVER[$name]);
            };
        }

        if (array_key_exists($name, $_ENV)) {
            $previousValue = $_ENV[$name];

            $restoreEnv = static fn () => $_SERVER[$name] = $previousValue;
        } else {
            $restoreEnv = static function () use ($name) {
                unset($_ENV[$name]);
            };
        }

        putenv($name.'='.$value);
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;

        return static function () use ($restoreServer, $restoreEnv, $name): void {
            putenv($name.'=');
            $restoreServer();
            $restoreEnv();
        };
    }

    private static function createCallable(int $id): callable
    {
        return static function () use ($id): void {
            echo $id;
        };
    }
}
