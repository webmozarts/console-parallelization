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

namespace Webmozarts\Console\Parallelization\Process;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Webmozarts\Console\Parallelization\FakeCallable;
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;
use function count;
use function explode;
use function sprintf;

/**
 * @covers \Webmozarts\Console\Parallelization\Process\SymfonyProcessLauncher
 *
 * @internal
 */
final class SymfonyProcessLauncherTest extends TestCase
{
    public function test_it_does_nothing_if_there_is_no_items(): void
    {
        $launcher = new SymfonyProcessLauncher(
            [],
            '',
            null,
            10,
            1,
            new FakeLogger(),
            FakeCallable::create(),
            FakeCallable::create(),
            new FakeProcessFactory(),
        );

        $launcher->run([]);

        $this->addToAssertionCount(1);
    }

    public function test_it_starts_initialized_processes_to_process_the_items(): void
    {
        $output = new BufferedOutput();

        $expectedCommandLine = 'php echo.php';
        $workingDirectory = __DIR__;
        $environmentVariables = ['TEST_SYMFONY_PROCESS' => '1'];
        $logger = new DummyLogger();
        $callback = static fn (string $type, string $buffer) => $output->writeln(
            sprintf(
                'type: %s; buffer: %s',
                $type,
                $buffer,
            ),
        );
        $processFactory = new DummyProcessFactory();

        $launcher = new SymfonyProcessLauncher(
            explode(' ', $expectedCommandLine),
            $workingDirectory,
            $environmentVariables,
            2,
            2,
            $logger,
            $callback,
            static function (): void {},
            $processFactory,
        );

        $launcher->run(['item1', 'item2']);

        $launchedProcesses = $processFactory->processes;

        $assertProcessStateIs = static function (DummyProcess $process) use (
            $expectedCommandLine,
            $workingDirectory,
            $environmentVariables,
            $callback
        ): void {
            self::assertSame($expectedCommandLine, $process->getCommandLine());
            self::assertSame($workingDirectory, $process->getWorkingDirectory());
            self::assertSame($environmentVariables, $process->getEnv());
            self::assertSame(
                [
                    [
                        'setEnv',
                        [$environmentVariables],
                    ],
                    ['setInput'],
                    [
                        'setTimeout',
                        [60.],
                    ],
                    [
                        'start',
                        [$callback],
                    ],
                ],
                $process->calls,
            );
        };

        foreach ($launchedProcesses as $launchedProcess) {
            $assertProcessStateIs($launchedProcess);
        }

        self::assertSame(
            [
                [
                    'logCommandStarted',
                    ['php echo.php'],
                ],
                [
                    'logCommandFinished',
                    [],
                ],
            ],
            $logger->records,
        );
    }

    /**
     * @dataProvider inputProvider
     */
    public function test_it_can_start_a_single_process_to_process_all_items(
        int $numberOfProcesses,
        int $segmentSize,
        array $items,
        string $expectedOutput,
        array $expectedProcessedItemsPerProcess,
        int $expectedNumberOfTicks,
        int $expectedExitCode
    ): void {
        $output = new BufferedOutput();

        $callback = static fn (string $type, string $buffer) => $output->writeln(
            sprintf(
                'type: %s; buffer: %s',
                $type,
                $buffer,
            ),
        );
        $processFactory = new DummyProcessFactory();
        $numberOfTicksRecorded = 0;

        $launcher = new SymfonyProcessLauncher(
            [],
            '',
            null,
            $numberOfProcesses,
            $segmentSize,
            new DummyLogger(),
            $callback,
            static function () use (&$numberOfTicksRecorded): void {
                ++$numberOfTicksRecorded;
            },
            $processFactory,
        );

        $exitCode = $launcher->run($items);

        self::assertSame($expectedOutput, $output->fetch());

        $launchedProcesses = $processFactory->processes;

        self::assertCount(
            count($expectedProcessedItemsPerProcess),
            $launchedProcesses,
            'Number of launched processes does not match.',
        );

        foreach ($launchedProcesses as $index => $launchedProcess) {
            $expectedProcessedItems = $expectedProcessedItemsPerProcess[$index];

            self::assertSame($expectedProcessedItems, $launchedProcess->processedItems);
        }

        self::assertSame(
            $expectedNumberOfTicks,
            $numberOfTicksRecorded,
            'Number of ticks recorded does not match.',
        );
        self::assertSame($expectedExitCode, $exitCode);
    }

    public static function inputProvider(): iterable
    {
        yield 'nominal' => [
            2,
            2,
            ['item1', 'item2', 'item3', 'item4', 'item5'],
            <<<'TXT'
                type: dummy; buffer: item1

                type: dummy; buffer: item2

                type: dummy; buffer: item3

                type: dummy; buffer: item4

                type: dummy; buffer: item5


                TXT,
            [
                ["item1\n", "item2\n"],
                ["item3\n", "item4\n"],
                ["item5\n"],
            ],
            3,
            7,
        ];

        yield 'single parallel process' => [
            1,
            10,
            ['item1', 'item2', 'item3', 'item4', 'item5'],
            <<<'TXT'
                type: dummy; buffer: item1

                type: dummy; buffer: item2

                type: dummy; buffer: item3

                type: dummy; buffer: item4

                type: dummy; buffer: item5


                TXT,
            [
                [
                    "item1\n",
                    "item2\n",
                    "item3\n",
                    "item4\n",
                    "item5\n",
                ],
            ],
            6,
            1,
        ];

        yield 'single parallel process with segment size smaller than the number of items' => [
            1,
            2,
            ['item1', 'item2', 'item3', 'item4', 'item5'],
            <<<'TXT'
                type: dummy; buffer: item1

                type: dummy; buffer: item2

                type: dummy; buffer: item3

                type: dummy; buffer: item4

                type: dummy; buffer: item5


                TXT,
            [
                ["item1\n", "item2\n"],
                ["item3\n", "item4\n"],
                ["item5\n"],
            ],
            6,
            7,
        ];
    }
}
