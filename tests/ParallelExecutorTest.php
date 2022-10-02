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

use function array_fill;
use function func_get_args;
use function implode;
use const PHP_EOL;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\DummyErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;
use Webmozarts\Console\Parallelization\Process\FakeProcessLauncherFactory;

/**
 * @covers \Webmozarts\Console\Parallelization\ParallelExecutor
 */
final class ParallelExecutorTest extends TestCase
{
    /**
     * @dataProvider childProcessProvider
     */
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
        $errorHandler = new DummyErrorHandler();
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
        self::assertSame($expectedExitCode, $exitCode);
        self::assertSame($expectedCalls, $calls);
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
        ItemProcessingErrorHandler $errorHandler,
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
            '',
            new InputDefinition(),
            $errorHandler,
            $childSourceStream,
            $batchSize,
            10,
            $runBeforeFirstCommand,
            $runAfterLastCommand,
            $runBeforeBatch,
            $runAfterBatch,
            $progressSymbol,
            __FILE__,
            __FILE__,
            __DIR__,
            null,
            new FakeProcessLauncherFactory(),
        );
    }
}
