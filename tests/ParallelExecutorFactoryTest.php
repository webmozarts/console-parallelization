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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Webmozarts\Console\Parallelization\ErrorHandler\FakeErrorHandler;
use Webmozarts\Console\Parallelization\Input\ChildCommandFactory;
use Webmozarts\Console\Parallelization\Process\FakeProcessLauncherFactory;
use function chr;
use function getcwd;
use function Safe\chdir;
use function sys_get_temp_dir;

/**
 * @internal
 */
#[CoversClass(ParallelExecutorFactory::class)]
final class ParallelExecutorFactoryTest extends TestCase
{
    private const FILE_1 = __DIR__;
    private const string FILE_2 = __DIR__.'/Logger/FakeLogger.php';
    private const string FILE_3 = __DIR__.'/ErrorHandler/FakeErrorHandler.php';

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
        $callable7 = self::createCallable(7);

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
            ->withProcessTick($callable7)
            ->build();

        $expected = new ParallelExecutor(
            $callable0,
            $callable1,
            $callable2,
            $errorHandler,
            $childSourceStream,
            $batchSize,
            $segmentSize,
            $callable3,
            $callable4,
            $callable5,
            $callable6,
            $progressSymbol,
            new ChildCommandFactory(
                [self::FILE_1],
                self::FILE_2,
                $commandName,
                $definition,
            ),
            self::FILE_3,
            $extraEnvironmentVariables,
            $processLauncherFactory,
            $callable7,
        );

        self::assertEquals($expected, $executor);
    }

    #[DataProvider('phpExecutableProvider')]
    public function test_it_escapes_the_php_executable_when_necessary(
        string|array $phpExecutable,
        array $expected,
    ): void {
        $commandName = 'import:items';
        $definition = new InputDefinition();
        $errorHandler = new FakeErrorHandler();

        $callable0 = self::createCallable(0);
        $callable1 = self::createCallable(1);
        $callable2 = self::createCallable(2);

        $factory = ParallelExecutorFactory::create(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
        )
            ->withPhpExecutable($phpExecutable);

        $phpExecutableReflection = (new ReflectionClass($factory::class))->getProperty('phpExecutable');
        $phpExecutableReflection->setAccessible(true);

        $actual = $phpExecutableReflection->getValue($factory);

        self::assertSame($expected, $actual);
    }

    public static function phpExecutableProvider(): iterable
    {
        yield 'simple path' => [
            '/path/to/php',
            ['/path/to/php'],
        ];

        yield 'path with space' => [
            '/path/to/my php',
            ['/path/to/my php'],
        ];

        yield 'path with space (array)' => [
            ['/path/to/my php'],
            ['/path/to/my php'],
        ];

        yield 'path with php setting (incorrect)' => [
            '/path/to/php -dmemory_limit=128M',
            ['/path/to/php -dmemory_limit=128M'],
        ];

        yield 'path with php setting (array)' => [
            ['/path/to/php', '-dmemory_limit=128M'],
            ['/path/to/php', '-dmemory_limit=128M'],
        ];

        yield 'path with space and with php setting (incorrect)' => [
            '/path/to/my php -dmemory_limit=128M',
            ['/path/to/my php -dmemory_limit=128M'],
        ];

        yield 'path with space and with php setting (array)' => [
            ['/path/to/my php', '-dmemory_limit=128M'],
            ['/path/to/my php', '-dmemory_limit=128M'],
        ];
    }

    public function test_it_sets_the_batch_size_to_the_segment_size_by_default(): void
    {
        $commandName = 'import:items';
        $definition = new InputDefinition();
        $errorHandler = new FakeErrorHandler();

        $callable0 = self::createCallable(0);
        $callable1 = self::createCallable(1);
        $callable2 = self::createCallable(2);

        $segmentSize = 20;

        $executor = ParallelExecutorFactory::create(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
        )
            ->withSegmentSize($segmentSize)
            ->build();

        $expected = ParallelExecutorFactory::create(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
        )
            ->withSegmentSize($segmentSize)
            ->withBatchSize($segmentSize)
            ->build();

        self::assertEquals($expected, $executor);
    }

    public function test_it_keeps_the_batch_size_set_if_changed(): void
    {
        $commandName = 'import:items';
        $definition = new InputDefinition();
        $errorHandler = new FakeErrorHandler();

        $callable0 = self::createCallable(0);
        $callable1 = self::createCallable(1);
        $callable2 = self::createCallable(2);

        $batchSize = 10;
        $segmentSize = 20;

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
            ->build();

        $expected = ParallelExecutorFactory::create(
            $callable0,
            $callable1,
            $callable2,
            $commandName,
            $definition,
            $errorHandler,
        )
            ->withSegmentSize($segmentSize)
            ->withBatchSize($batchSize)
            ->build();

        self::assertEquals($expected, $executor);
    }

    #[DataProvider('defaultValuesProvider')]
    public function test_it_can_create_an_executor_with_default_values(
        array $environmentVariables,
        string $workingDirectory,
        string $expectedSymbol,
        string $expectedPhpExecutable,
        string $expectedScriptPath,
        string $expectedWorkingDirectory,
    ): void {
        $cleanUpWorkingDirectory = self::moveToWorkingDirectory($workingDirectory);
        $cleanUpEnvironmentVariables = EnvironmentVariables::setVariables($environmentVariables);

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

        // This can happen e.g. when executed with Docker.
        // See https://github.com/webmozarts/console-parallelization/issues/204.
        yield 'PWD is not set' => [
            [
                'PHP_BINARY' => $phpExecutable,
                'PWD' => null,
                'SCRIPT_NAME' => __DIR__.'/../bin/console',
            ],
            $workingDirectory,
            $progressSymbol,
            $phpExecutable,
            $expectedScriptPath,
            $workingDirectory,
        ];
    }

    // See https://github.com/webmozarts/console-parallelization/issues/223
    public function test_it_can_execute_the_command_even_if_the_script_is_different(): void
    {
        $filesystem = new Filesystem();
        $tmp = sys_get_temp_dir().'/WebmozartsConsoleParallelizationTest';

        $filesystem->mkdir($tmp);

        try {
            $process = Process::fromShellCommandline(
                'cd '.$tmp.'; '.__DIR__.'/../bin/console absolute-script-path --quiet',
            );

            $process->run();

            self::assertSame(
                0,
                $process->getExitCode(),
                $process->getOutput().$process->getErrorOutput(),
            );
        } finally {
            $filesystem->remove($tmp);
        }
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

    private static function createCallable(int $id): callable
    {
        return static function () use ($id): void {
            echo $id;
        };
    }
}
