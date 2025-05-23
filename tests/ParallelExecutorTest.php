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

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\BinomialSumErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\FakeErrorHandler;
use Webmozarts\Console\Parallelization\Input\ChildCommandFactory;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;
use Webmozarts\Console\Parallelization\Process\FakeProcessLauncherFactory;
use Webmozarts\Console\Parallelization\Process\ProcessLauncher;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use function array_fill;
use function func_get_args;
use function getcwd;
use function implode;
use function str_repeat;
use const PHP_EOL;

/**
 * @internal
 */
#[CoversClass(ParallelExecutor::class)]
final class ParallelExecutorTest extends TestCase
{
    use ProphecyTrait;

    #[DataProvider('childProcessProvider')]
    public function test_it_can_execute_a_child_process(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        BufferedOutput $output,
        string $sourceStreamString,
        int $batchSize,
        string $progressSymbol,
        string $expectedOutput,
        int $expectedExitCode,
        array $expectedCalls
    ): void {
        $calls = [];
        $errorHandler = new FakeErrorHandler();
        $sourceStream = StringStream::fromString($sourceStreamString);

        $createCallable = static function (string $name) use (&$calls) {
            return static function () use ($name, &$calls): void {
                $calls[] = [$name, func_get_args()];
            };
        };

        $executor = self::createChildProcessExecutor(
            $createCallable('runSingleCommand'),
            $errorHandler,
            $sourceStream,
            $batchSize,
            $createCallable('runBeforeFirstCommand'),
            $createCallable('runAfterLastCommand'),
            $createCallable('runBeforeBatch'),
            $createCallable('runAfterBatch'),
            $progressSymbol,
        );

        $exitCode = $executor->execute(
            $parallelizationInput,
            $input,
            $output,
            new FakeLogger(),
        );

        self::assertSame($expectedOutput, $output->fetch());
        self::assertSame($expectedCalls, $calls);
        self::assertSame($expectedExitCode, $exitCode);
    }

    public static function childProcessProvider(): iterable
    {
        $progressSymbol = '👉';

        $createExpectedOutput = static fn (int $numberOfItemsProcessed) => implode(
            '',
            array_fill(0, $numberOfItemsProcessed, $progressSymbol),
        );

        yield 'more items than batch size' => (static function () use (
            $progressSymbol,
            $createExpectedOutput
        ) {
            $input = new StringInput('');
            $output = new BufferedOutput();

            $items = [
                'item1',
                'item2',
                'item3',
            ];

            return [
                new ParallelizationInput(
                    true,
                    5,
                    null,
                    true,
                    null,
                    null,
                ),
                $input,
                $output,
                implode(PHP_EOL, $items),
                2,
                $progressSymbol,
                $createExpectedOutput(3),
                0,
                [
                    [
                        'runBeforeBatch',
                        [$input, $output, [$items[0], $items[1]]],
                    ],
                    [
                        'runSingleCommand',
                        [$items[0], $input, $output],
                    ],
                    [
                        'runSingleCommand',
                        [$items[1], $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, [$items[0], $items[1]]],
                    ],
                    [
                        'runBeforeBatch',
                        [$input, $output, [$items[2]]],
                    ],
                    [
                        'runSingleCommand',
                        [$items[2], $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, [$items[2]]],
                    ],
                ],
                [],
            ];
        })();

        yield 'less items than batch size' => (static function () use (
            $progressSymbol,
            $createExpectedOutput
        ) {
            $input = new StringInput('');
            $output = new BufferedOutput();

            return [
                new ParallelizationInput(
                    true,
                    5,
                    null,
                    true,
                    null,
                    null,
                ),
                $input,
                $output,
                'item1'.PHP_EOL,
                2,
                $progressSymbol,
                $createExpectedOutput(1),
                0,
                [
                    [
                        'runBeforeBatch',
                        [$input, $output, ['item1']],
                    ],
                    [
                        'runSingleCommand',
                        ['item1', $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, ['item1']],
                    ],
                ],
                [],
            ];
        })();

        yield 'no item' => (static function () use (
            $progressSymbol,
            $createExpectedOutput
        ) {
            $input = new StringInput('');
            $output = new BufferedOutput();

            return [
                new ParallelizationInput(
                    true,
                    5,
                    'item1',
                    true,
                    null,
                    null,
                ),
                $input,
                $output,
                '',
                2,
                $progressSymbol,
                $createExpectedOutput(0),
                0,
                [],
                [],
            ];
        })();

        yield 'overridden batch and segment size' => (static function () use (
            $progressSymbol,
            $createExpectedOutput
        ) {
            $input = new StringInput('');
            $output = new BufferedOutput();

            $items = [
                'item1',
                'item2',
                'item3',
            ];

            return [
                new ParallelizationInput(
                    true,
                    5,
                    null,
                    true,
                    1,
                    null,
                ),
                $input,
                $output,
                implode(PHP_EOL, $items),
                2,
                $progressSymbol,
                $createExpectedOutput(3),
                0,
                [
                    [
                        'runBeforeBatch',
                        [$input, $output, [$items[0]]],
                    ],
                    [
                        'runSingleCommand',
                        [$items[0], $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, [$items[0]]],
                    ],
                    [
                        'runBeforeBatch',
                        [$input, $output, [$items[1]]],
                    ],
                    [
                        'runSingleCommand',
                        [$items[1], $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, [$items[1]]],
                    ],
                    [
                        'runBeforeBatch',
                        [$input, $output, [$items[2]]],
                    ],
                    [
                        'runSingleCommand',
                        [$items[2], $input, $output],
                    ],
                    [
                        'runAfterBatch',
                        [$input, $output, [$items[2]]],
                    ],
                ],
                [],
            ];
        })();
    }

    public function test_it_handles_processing_failures_in_child_processes(): void
    {
        $parallelizationInput = new ParallelizationInput(
            true,
            5,
            null,
            true,
            null,
            null,
        );
        $input = new StringInput('');
        $output = new BufferedOutput();
        $items = [
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
        ];
        $sourceStreamString = implode(PHP_EOL, $items);
        $batchSize = 2;
        $progressSymbol = '👉';
        $calls = [];
        $errorHandler = new BinomialSumErrorHandler();
        $sourceStream = StringStream::fromString($sourceStreamString);
        $error = new Error('Processing failed.');
        $logger = new FakeLogger();

        $createCallable = static function (string $name) use (&$calls) {
            return static function () use ($name, &$calls): void {
                $calls[] = [$name, func_get_args()];
            };
        };

        $executor = self::createChildProcessExecutor(
            static function (string $item) use ($error, &$calls): void {
                $calls[] = ['runSingleCommand', func_get_args()];

                if ('item2' === $item || 'item4' === $item) {
                    throw $error;
                }
            },
            $errorHandler,
            $sourceStream,
            $batchSize,
            $createCallable('runBeforeFirstCommand'),
            $createCallable('runAfterLastCommand'),
            $createCallable('runBeforeBatch'),
            $createCallable('runAfterBatch'),
            $progressSymbol,
        );

        $expectedOutput = str_repeat($progressSymbol, 5);
        $expectedExitCode = 3;
        $expectedCalls = [
            [
                'runBeforeBatch',
                [$input, $output, ['item1', 'item2']],
            ],
            [
                'runSingleCommand',
                ['item1', $input, $output],
            ],
            [
                'runSingleCommand',
                ['item2', $input, $output],
            ],
            [
                'runAfterBatch',
                [$input, $output, ['item1', 'item2']],
            ],
            [
                'runBeforeBatch',
                [$input, $output, ['item3', 'item4']],
            ],
            [
                'runSingleCommand',
                ['item3', $input, $output],
            ],
            [
                'runSingleCommand',
                ['item4', $input, $output],
            ],
            [
                'runAfterBatch',
                [$input, $output, ['item3', 'item4']],
            ],
            [
                'runBeforeBatch',
                [$input, $output, ['item5']],
            ],
            [
                'runSingleCommand',
                ['item5', $input, $output],
            ],
            [
                'runAfterBatch',
                [$input, $output, ['item5']],
            ],
        ];
        $expectedErrors = [
            [
                'item2',
                $error,
                $logger,
            ],
            [
                'item4',
                $error,
                $logger,
            ],
        ];

        $exitCode = $executor->execute(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );

        self::assertSame($expectedOutput, $output->fetch());
        self::assertSame($expectedCalls, $calls);
        self::assertSame($expectedErrors, $errorHandler->calls);
        self::assertSame($expectedExitCode, $exitCode);
    }

    public function test_it_can_launch_configured_child_processes(): void
    {
        $inputBatchSize = 3;
        $inputSegmentSize = 5;

        $parallelizationInput = new ParallelizationInput(
            false,
            2,
            null,
            false,
            $inputBatchSize,
            $inputSegmentSize,
        );

        $input = new ArrayInput([
            'item' => 'item3',
            'groupId' => 'group2',
            '--child' => null,
            '--processes' => '2',
            '--opt' => 'val',
            '--batch-size' => (string) $inputBatchSize,
            '--segment-size' => (string) $inputSegmentSize,
        ]);

        $commandDefinition = new InputDefinition([
            new InputArgument(
                'item',
                InputArgument::REQUIRED,
            ),
            new InputArgument(
                'groupId',
                InputArgument::REQUIRED,
            ),
            new InputArgument(
                'optArg',
                InputArgument::OPTIONAL,
                '',
                '',
            ),
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            new InputOption(
                'child',
                null,
                InputOption::VALUE_NONE,
            ),
            new InputOption(
                'processes',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            new InputOption(
                'batch-size',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            new InputOption(
                'segment-size',
                null,
                InputOption::VALUE_REQUIRED,
            ),
        ]);
        $input->bind($commandDefinition);

        $output = new NullOutput();
        $errorHandler = new FakeErrorHandler();
        $logger = new DummyLogger();

        $noop = static function (): void {};

        $items = ['item0', 'item1', 'item2'];
        $commandName = 'import:something';
        $phpExecutable = __FILE__;
        $scriptPath = __DIR__.'/../bin/console';
        $workingDirectory = __DIR__;
        $extraEnvironmentVariables = ['EXTRA_ENV' => '1'];

        $processLauncherProphecy = $this->prophesize(ProcessLauncher::class);
        $processLauncherProphecy
            ->run($items)
            ->shouldBeCalled();

        $processLauncherFactoryProphecy = $this->prophesize(ProcessLauncherFactory::class);
        $processLauncherFactoryProphecy
            ->create(
                [
                    $phpExecutable,
                    $scriptPath,
                    $commandName,
                    'group2',
                    '--child',
                    '--opt=val',
                    '--batch-size='.$inputBatchSize,
                    '--segment-size='.$inputSegmentSize,
                ],
                $workingDirectory,
                $extraEnvironmentVariables,
                Argument::any(),
                $inputSegmentSize,
                $logger,
                Argument::type('callable'),
                Argument::type('callable'),
            )
            ->willReturn($processLauncherProphecy->reveal());

        $executor = new ParallelExecutor(
            static fn () => $items,
            $noop,
            static fn (int $itemCount) => 0 === $itemCount ? 'item' : 'items',
            $errorHandler,
            StringStream::fromString(''),
            1,
            2,
            $noop,
            $noop,
            $noop,
            $noop,
            'ø',
            new ChildCommandFactory(
                [$phpExecutable],
                $scriptPath,
                $commandName,
                $commandDefinition,
            ),
            $workingDirectory,
            $extraEnvironmentVariables,
            $processLauncherFactoryProphecy->reveal(),
            static function (): void {},
        );

        $executor->execute(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );

        $processLauncherProphecy
            ->run(Argument::cetera())
            ->shouldHaveBeenCalledTimes(1);
        $processLauncherFactoryProphecy
            ->create(Argument::cetera())
            ->shouldHaveBeenCalledTimes(1);
    }

    #[DataProvider('childProcessSpawnerProvider')]
    public function test_it_can_can_launch_child_processes_or_process_within_the_main_process(
        ParallelizationInput $parallelizationInput,
        int $segmentSize,
        array $items,
        bool $expected
    ): void {
        $noop = static function (): void {};

        $processLauncherFactory = $this->createProcessLauncherFactory($expected);

        $executor = self::createMainProcessExecutor(
            $items,
            $noop,
            new FakeErrorHandler(),
            2,
            $segmentSize,
            $noop,
            $noop,
            $noop,
            $noop,
            'ø',
            $processLauncherFactory,
        );

        $executor->execute(
            $parallelizationInput,
            new StringInput(''),
            new NullOutput(),
            new DummyLogger(),
        );

        $this->addToAssertionCount(1);
    }

    public static function childProcessSpawnerProvider(): iterable
    {
        $createSet = static fn (
            int $itemCount,
            bool $mainProcess,
            int $segmentSize,
            int $numberOfProcesses,
            bool $expectedChildProcessesSpawned
        ) => [
            new ParallelizationInput(
                false,
                $numberOfProcesses,
                null,
                false,
                null,
                null,
            ),
            $segmentSize,
            array_fill(0, $itemCount, 'itemX'),
            $expectedChildProcessesSpawned,
        ];

        yield 'do not execute in main process' => $createSet(
            3,
            false,
            2,
            2,
            true,
        );
    }

    #[DataProvider('mainProcessProvider')]
    public function test_it_can_execute_a_main_process(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        BufferedOutput $output,
        array $items,
        int $batchSize,
        int $segmentSize,
        string $progressSymbol,
        string $expectedOutput,
        int $expectedExitCode,
        array $expectedCalls,
        array $expectedLogRecords,
        bool $expectedChildProcessesSpawned
    ): void {
        $calls = [];
        $errorHandler = new BinomialSumErrorHandler();
        $logger = new DummyLogger();

        $createCallable = static function (string $name) use (&$calls) {
            return static function () use ($name, &$calls): void {
                $calls[] = [$name, func_get_args()];
            };
        };

        $processLauncherFactory = $this->createProcessLauncherFactory(
            $expectedChildProcessesSpawned,
        );

        $executor = self::createMainProcessExecutor(
            $items,
            $createCallable('runSingleCommand'),
            $errorHandler,
            $batchSize,
            $segmentSize,
            $createCallable('runBeforeFirstCommand'),
            $createCallable('runAfterLastCommand'),
            $createCallable('runBeforeBatch'),
            $createCallable('runAfterBatch'),
            $progressSymbol,
            $processLauncherFactory,
        );

        $exitCode = $executor->execute(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );

        self::assertSame($expectedOutput, $output->fetch());
        self::assertSame($expectedCalls, $calls);
        self::assertEquals($expectedLogRecords, $logger->records);
        self::assertSame($expectedExitCode, $exitCode);
    }

    public static function mainProcessProvider(): iterable
    {
        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[withChild] ',
            self::mainProcessWithChildProcessLaunchedProvider(),
        );

        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[withoutChild] ',
            self::mainProcessWithoutChildProcessLaunchedProvider(),
        );
    }

    public function test_it_processes_the_child_processes_output(): void
    {
        $parallelizationInput = new ParallelizationInput(
            false,
            2,
            null,
            false,
            null,
            null,
        );
        $batchSize = 2;
        $segmentSize = 2;
        $input = new StringInput('');
        $output = new BufferedOutput();
        $items = [
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
        ];
        $progressSymbol = '👉';
        $calls = [];
        $errorHandler = new FakeErrorHandler();
        $logger = new DummyLogger();

        $createCallable = static function (string $name) use (&$calls) {
            return static function () use ($name, &$calls): void {
                $calls[] = [$name, func_get_args()];
            };
        };

        $processOutput = FakeCallable::create();

        $processLauncherProphecy = $this->prophesize(ProcessLauncher::class);
        $processLauncherProphecy
            ->run(Argument::cetera())
            ->will(static function () use ($progressSymbol, &$processOutput): int {
                $processOutput(10, null, 'test', $progressSymbol);
                $processOutput(10, null, 'test', 'FOO');    // unexpected output
                $processOutput(10, null, 'test', $progressSymbol);
                $processOutput(10, null, 'test', $progressSymbol.$progressSymbol.$progressSymbol);  // multi-step
                $processOutput(10, null, 'test', $progressSymbol);

                return 0;
            });

        $processLauncherFactoryProphecy = $this->prophesize(ProcessLauncherFactory::class);
        $processLauncherFactoryProphecy
            ->create(Argument::cetera())
            ->will(static function (array $arguments) use ($processLauncherProphecy, &$processOutput) {
                $processOutput = $arguments[6];

                return $processLauncherProphecy->reveal();
            });

        $executor = self::createMainProcessExecutor(
            $items,
            $createCallable('runSingleCommand'),
            $errorHandler,
            $batchSize,
            $segmentSize,
            $createCallable('runBeforeFirstCommand'),
            $createCallable('runAfterLastCommand'),
            $createCallable('runBeforeBatch'),
            $createCallable('runAfterBatch'),
            $progressSymbol,
            $processLauncherFactoryProphecy->reveal(),
        );

        $expectedOutput = '';
        $expectedCalls = [
            [
                'runBeforeFirstCommand',
                [$input, $output],
            ],
            [
                'runAfterLastCommand',
                [$input, $output],
            ],
        ];
        $expectedLogRecords = [
            [
                'logConfiguration',
                [
                    new Configuration(
                        2,
                        $segmentSize,
                        3,
                        3,
                    ),
                    $batchSize,
                    5,
                    'items',
                    true,
                ],
            ],
            [
                'logStart',
                [5],
            ],
            [
                'logAdvance',
                [1],
            ],
            [
                'logUnexpectedChildProcessOutput',
                [10, null, 'test', 'FOO', $progressSymbol],
            ],
            [
                'logAdvance',
                [0],
            ],
            [
                'logAdvance',
                [1],
            ],
            [
                'logAdvance',
                [3],
            ],
            [
                'logAdvance',
                [1],
            ],
            [
                'logFinish',
                ['items'],
            ],
        ];
        $expectedExitCode = 0;

        $exitCode = $executor->execute(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );

        self::assertSame($expectedOutput, $output->fetch());
        self::assertSame($expectedCalls, $calls);
        self::assertEquals($expectedLogRecords, $logger->records);
        self::assertSame($expectedExitCode, $exitCode);
    }

    #[DataProvider('invalidExecutorProvider')]
    public function test_it_cannot_create_an_executor_with_an_invalid_value(
        ParallelExecutorFactory $factory,
        string $expectedExceptionMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $factory->build();
    }

    public static function invalidExecutorProvider(): iterable
    {
        $createFactory = static fn () => ParallelExecutorFactory::create(
            FakeCallable::create(),
            FakeCallable::create(),
            FakeCallable::create(),
            'test:command',
            new InputDefinition(),
            new FakeErrorHandler(),
        );

        yield 'invalid batch size' => [
            // @phpstan-ignore-next-line
            $createFactory()->withBatchSize(0),
            'Expected the batch size to be 1 or greater. Got "0".',
        ];

        yield 'invalid segment size' => [
            // @phpstan-ignore-next-line
            $createFactory()->withSegmentSize(0),
            'Expected the segment size to be 1 or greater. Got "0".',
        ];

        yield 'invalid script path' => [
            $createFactory()->withScriptPath('/path/to/nowhere'),
            'The script file could not be found at the path "/path/to/nowhere" (working directory: '.getcwd().')',
        ];

        yield 'invalid progress symbol' => [
            $createFactory()->withProgressSymbol('foo'),
            'Expected the progress symbol length to be 1. Got "3" for "foo".',
        ];

        yield 'invalid progress symbol (emoji)' => [
            $createFactory()->withProgressSymbol('👹👹'),
            'Expected the progress symbol length to be 1. Got "2" for "👹👹".',
        ];
    }

    private static function mainProcessWithChildProcessLaunchedProvider(): iterable
    {
        $batchSize = 2;
        $segmentSize = 2;
        $numberOfProcesses = 2;
        $mainProcess = false;
        $numberOfSegments = 2;
        $totalNumberOfBatches = 2;

        $input = new StringInput('');
        $output = new BufferedOutput();

        $items = [
            'item1',
            'item2',
            'item3',
        ];

        yield 'nominal' => [
            new ParallelizationInput(
                $mainProcess,
                $numberOfProcesses,
                null,
                false,
                null,
                null,
            ),
            $input,
            $output,
            $items,
            $batchSize,
            $segmentSize,
            '👉',
            '',
            0,
            [
                [
                    'runBeforeFirstCommand',
                    [$input, $output],
                ],
                [
                    'runAfterLastCommand',
                    [$input, $output],
                ],
            ],
            [
                [
                    'logConfiguration',
                    [
                        new Configuration(
                            $numberOfProcesses,
                            $segmentSize,
                            $numberOfSegments,
                            $totalNumberOfBatches,
                        ),
                        $batchSize,
                        3,
                        'items',
                        true,
                    ],
                ],
                [
                    'logStart',
                    [3],
                ],
                [
                    'logFinish',
                    ['items'],
                ],
            ],
            true,
        ];

        yield 'with overridden batch & segment size' => [
            new ParallelizationInput(
                $mainProcess,
                $numberOfProcesses,
                null,
                false,
                10,
                20,
            ),
            $input,
            $output,
            $items,
            $batchSize,
            $segmentSize,
            '👉',
            '',
            0,
            [
                [
                    'runBeforeFirstCommand',
                    [$input, $output],
                ],
                [
                    'runAfterLastCommand',
                    [$input, $output],
                ],
            ],
            [
                [
                    'logConfiguration',
                    [
                        new Configuration(
                            1,
                            20,
                            1,
                            1,
                        ),
                        10,
                        3,
                        'items',
                        true,
                    ],
                ],
                [
                    'logStart',
                    [3],
                ],
                [
                    'logFinish',
                    ['items'],
                ],
            ],
            true,
        ];
    }

    /** @noinspection NestedTernaryOperatorInspection */
    private static function mainProcessWithoutChildProcessLaunchedProvider(): iterable
    {
        $batchSize = 2;
        $segmentSize = 3;
        $numberOfProcesses = 1;
        $numberOfSegments = 1;
        $totalNumberOfBatches = 2;

        $input = new StringInput('');
        $output = new BufferedOutput();

        yield [
            new ParallelizationInput(
                true,
                $numberOfProcesses,
                null,
                false,
                null,
                null,
            ),
            $input,
            $output,
            ['item1', 'item2', 'item3'],
            $batchSize,
            $segmentSize,
            '👉',
            '',
            0,
            [
                [
                    'runBeforeFirstCommand',
                    [$input, $output],
                ],
                [
                    'runBeforeBatch',
                    [$input, $output, ['item1', 'item2']],
                ],
                [
                    'runSingleCommand',
                    ['item1', $input, $output],
                ],
                [
                    'runSingleCommand',
                    ['item2', $input, $output],
                ],
                [
                    'runAfterBatch',
                    [$input, $output, ['item1', 'item2']],
                ],
                [
                    'runBeforeBatch',
                    [$input, $output, ['item3']],
                ],
                [
                    'runSingleCommand',
                    ['item3', $input, $output],
                ],
                [
                    'runAfterBatch',
                    [$input, $output, ['item3']],
                ],
                [
                    'runAfterLastCommand',
                    [$input, $output],
                ],
            ],
            [
                [
                    'logConfiguration',
                    [
                        new Configuration(
                            $numberOfProcesses,
                            1,
                            $numberOfSegments,
                            $totalNumberOfBatches,
                        ),
                        $batchSize,
                        3,
                        'items',
                        false,
                    ],
                ],
                [
                    'logStart',
                    [3],
                ],
                [
                    'logAdvance',
                    [],
                ],
                [
                    'logAdvance',
                    [],
                ],
                [
                    'logAdvance',
                    [],
                ],
                [
                    'logFinish',
                    ['items'],
                ],
            ],
            false,
        ];
    }

    /**
     * @param callable(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param resource                                                     $childSourceStream
     * @param positive-int                                                 $batchSize
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     */
    private static function createChildProcessExecutor(
        callable $runSingleCommand,
        ErrorHandler $errorHandler,
        $childSourceStream,
        int $batchSize,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        string $progressSymbol
    ): ParallelExecutor {
        return new ParallelExecutor(
            FakeCallable::create(),
            $runSingleCommand,
            FakeCallable::create(),
            $errorHandler,
            $childSourceStream,
            $batchSize,
            10,
            $runBeforeFirstCommand,
            $runAfterLastCommand,
            $runBeforeBatch,
            $runAfterBatch,
            $progressSymbol,
            new ChildCommandFactory(
                [__FILE__],
                __FILE__,
                '',
                new InputDefinition(),
            ),
            __DIR__,
            null,
            new FakeProcessLauncherFactory(),
            static function (): void {},
        );
    }

    /**
     * @param list<string>                                                 $items
     * @param callable(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param positive-int                                                 $batchSize
     * @param positive-int                                                 $segmentSize
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     */
    private static function createMainProcessExecutor(
        array $items,
        callable $runSingleCommand,
        ErrorHandler $errorHandler,
        int $batchSize,
        int $segmentSize,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        string $progressSymbol,
        ProcessLauncherFactory $processLauncherFactory
    ): ParallelExecutor {
        return new ParallelExecutor(
            static fn () => $items,
            $runSingleCommand,
            static fn (int $itemCount) => 0 === $itemCount ? 'item' : 'items',
            $errorHandler,
            StringStream::fromString(''),
            $batchSize,
            $segmentSize,
            $runBeforeFirstCommand,
            $runAfterLastCommand,
            $runBeforeBatch,
            $runAfterBatch,
            $progressSymbol,
            new ChildCommandFactory(
                [__FILE__],
                __FILE__,
                'import:something',
                new InputDefinition([
                    new InputArgument(
                        'groupId',
                        InputArgument::REQUIRED,
                    ),
                ]),
            ),
            __DIR__,
            null,
            $processLauncherFactory,
            static function (): void {},
        );
    }

    private function createProcessLauncherFactory(bool $spawnChildProcesses): ProcessLauncherFactory
    {
        if (!$spawnChildProcesses) {
            return new FakeProcessLauncherFactory();
        }

        $processLauncherProphecy = $this->prophesize(ProcessLauncher::class);
        $processLauncherProphecy
            ->run(Argument::cetera())
            ->shouldBeCalled();

        $processLauncherFactoryProphecy = $this->prophesize(ProcessLauncherFactory::class);
        $processLauncherFactoryProphecy
            ->create(Argument::cetera())
            ->willReturn($processLauncherProphecy->reveal());

        return $processLauncherFactoryProphecy->reveal();
    }
}
