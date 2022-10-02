<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Process;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;
use Webmozarts\Console\Parallelization\FakeCallable;
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use Webmozarts\Console\Parallelization\Logger\FakeLogger;
use function count;
use function explode;
use function getcwd;
use function sprintf;

/**
 * @covers \Webmozarts\Console\Parallelization\Process\SymfonyProcessLauncher
 */
final class SymfonyProcessLauncherTest extends TestCase
{
    // TODO: test logger
    // TODO: split tests of; processes are initialsed correctly and they are run correctly

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

    /**
     * @dataProvider inputProvider
     */
    public function test_it_can_start_a_single_process_to_process_all_items(
        int $numberOfProcesses,
        int $segmentSize,
        array $items,
        string $expectedOutput,
        array $expectedProcessedItemsPerProcess,
        int $expectedNumberOfTicks
    ): void
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
        $numberOfTicksRecorded = 0;

        $launcher = new SymfonyProcessLauncher(
            explode(' ', $expectedCommandLine),
            $workingDirectory,
            $environmentVariables,
            $numberOfProcesses,
            $segmentSize,
            $logger,
            $callback,
            static function () use (&$numberOfTicksRecorded): void {
                $numberOfTicksRecorded++;
            },
            $processFactory,
        );

        $launcher->run($items);

        self::assertSame($expectedOutput, $output->fetch());

        $launchedProcesses = $processFactory->processes;

        self::assertCount(
            count($expectedProcessedItemsPerProcess),
            $launchedProcesses,
            'Number of launched processes does not match.',
        );

        $assertProcessStateIs = static function (
            array $expectedProcessItems,
            DummyProcess $process
        ) use (
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
            self::assertSame($expectedProcessItems, $process->processedItems);
        };

        foreach ($launchedProcesses as $index => $launchedProcess) {
            $expectedProcessedItems = $expectedProcessedItemsPerProcess[$index];

            $assertProcessStateIs($expectedProcessedItems, $launchedProcess);
        }

        self::assertSame(
            $expectedNumberOfTicks,
            $numberOfTicksRecorded,
            'Number of ticks recorded does not match.',
        );
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
        ];
    }
}
