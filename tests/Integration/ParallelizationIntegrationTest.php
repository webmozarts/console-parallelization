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

namespace Webmozarts\Console\Parallelization\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Webmozarts\Console\Parallelization\Fixtures\Command\ImportMoviesCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\ImportUnknownMoviesCountCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\NoSubProcessCommand;
use Webmozarts\Console\Parallelization\Integration\App\BareKernel;
use function preg_replace;
use function str_replace;

/**
 * @internal
 */
#[CoversNothing]
class ParallelizationIntegrationTest extends TestCase
{
    private ImportMoviesCommand $importMoviesCommand;
    private CommandTester $importMoviesCommandTester;

    private ImportUnknownMoviesCountCommand $importUnknownMoviesCountCommand;
    private CommandTester $importUnknownMoviesCountCommandTester;

    private NoSubProcessCommand $noSubProcessCommand;
    private CommandTester $noSubProcessCommandTester;

    protected function setUp(): void
    {
        $this->importMoviesCommand = (new Application(new BareKernel()))->add(new ImportMoviesCommand());
        $this->importMoviesCommandTester = new CommandTester($this->importMoviesCommand);

        $this->importUnknownMoviesCountCommand = (new Application(new BareKernel()))->add(new ImportUnknownMoviesCountCommand());
        $this->importUnknownMoviesCountCommandTester = new CommandTester($this->importUnknownMoviesCountCommand);

        $this->noSubProcessCommand = (new Application(new BareKernel()))->add(new NoSubProcessCommand());
        $this->noSubProcessCommandTester = new CommandTester($this->noSubProcessCommand);
    }

    protected function tearDown(): void
    {
        TestLogger::clearLogfile();
    }

    public function test_it_can_run_the_command_without_sub_processes(): void
    {
        $commandTester = $this->noSubProcessCommandTester;

        $commandTester->execute(
            [
                'command' => 'test:no-subprocess',
                '--main-process' => null,
            ],
            ['interactive' => true],
        );

        $expected = <<<'EOF'
            Processing 5 items, batches of 2, 3 batches, in the current process.

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 5 items.

            EOF;

        $actual = OutputNormalizer::removeIntermediateFixedProgressBars(
            $this->getOutput($commandTester),
        );

        self::assertSame($expected, $actual, $actual);
    }

    public function test_it_processes_the_item_in_the_main_process_if_an_item_is_passed(): void
    {
        $commandTester = $this->noSubProcessCommandTester;

        $commandTester->execute(
            [
                'command' => 'test:no-subprocess',
                'item' => 'item0',
            ],
            ['interactive' => true],
        );

        $expected = <<<'EOF'
            Processing 1 item, batches of 2, 1 batch, in the current process.

             0/1 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             1/1 [============================] 100% 10 secs/10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 1 item.

            EOF;

        $actual = OutputNormalizer::removeIntermediateFixedProgressBars(
            $this->getOutput($commandTester),
        );

        self::assertSame($expected, $actual, $actual);
    }

    public function test_it_uses_a_sub_process_if_only_one_process_is_used(): void
    {
        $commandTester = $this->noSubProcessCommandTester;

        $commandTester->execute(
            [
                'command' => 'test:no-subprocess',
                '--processes' => '1',
            ],
            ['interactive' => true],
        );

        $output = $this->getOutput($commandTester);

        self::assertStringContainsString('Expected to be executed within the main process.', $output);
    }

    public function test_it_can_run_the_command_with_multiple_processes(): void
    {
        $commandTester = $this->importMoviesCommandTester;

        $commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => '2',
            ],
            ['interactive' => true],
        );

        $expected = <<<'EOF'
            Processing 5 movies in segments of 2, batches of 2, 3 rounds, 3 batches, with 2 parallel child processes.

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 5 movies.

            EOF;

        $actual = OutputNormalizer::removeIntermediateFixedProgressBars(
            $this->getOutput($commandTester),
        );

        self::assertSame($expected, $actual, $actual);
    }

    public function test_it_can_run_the_command_with_multiple_processes_without_knowing_the_number_of_items_to_import(): void
    {
        $commandTester = $this->importUnknownMoviesCountCommandTester;

        $commandTester->execute(
            [
                'command' => 'import:movies-unknown-count',
                '--processes' => '2',
            ],
            ['interactive' => true],
        );

        $expected = <<<'EOF'
            Processing ??? movies in segments of 2, batches of 2, with 2 parallel child processes.

                0 [>---------------------------] 10 secs 10.0 MiB
                5 [----->----------------------] 10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 5 movies.

            EOF;

        $actual = OutputNormalizer::removeIntermediateNonFixedProgressBars(
            $this->getOutput($commandTester),
            5,
        );

        self::assertSame($expected, $actual, $actual);
    }

    public function test_it_can_run_the_command_with_multiple_processes_in_very_verbose_mode(): void
    {
        $commandTester = $this->importMoviesCommandTester;

        $commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => '2',
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE,
            ],
        );

        $expectedWithNoDebugMode = <<<'EOF'
            Processing 5 movies in segments of 2, batches of 2, 3 rounds, 3 batches, with 2 parallel child processes.

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 5 movies.

            EOF;

        $actual = $this->getOutput($commandTester);

        $removeProcessStartedOutput = static fn (string $output) => preg_replace(
            "~\n?\\[notice\\] Started process #\\d \\(PID \\d+\\): '/path/to/php' '/path/to/work-dir/bin/console' 'import:movies' '--child'\n~",
            '',
            $output,
        );
        $removeProcessStoppedOutput = static fn (string $output) => preg_replace(
            '~\[notice\] Stopped process #\d\n~',
            '',
            $output,
        );
        $removeUnstableOutput = static fn (string $output) => str_replace(
            "MiB\n\n 5/5",
            "MiB\n 5/5",
            $output,
        );

        $outputWithoutExtraDebugInfo = $removeUnstableOutput(
            $removeProcessStartedOutput(
                $removeProcessStoppedOutput(
                    OutputNormalizer::removeIntermediateFixedProgressBars($actual),
                ),
            ),
        );

        $expectedChildProcessesCount = 3;
        $expectedCommandStartedLine = '[notice] Started process';
        $expectedCommandFinishedLine = '[notice] Stopped process';

        self::assertSame($expectedWithNoDebugMode, $outputWithoutExtraDebugInfo, $outputWithoutExtraDebugInfo);
        self::assertSame($expectedChildProcessesCount, mb_substr_count($actual, $expectedCommandStartedLine));
        self::assertSame($expectedChildProcessesCount, mb_substr_count($actual, $expectedCommandFinishedLine));
    }

    private function getOutput(CommandTester $commandTester): string
    {
        $output = $commandTester->getDisplay(true);

        return OutputNormalizer::normalize($output);
    }
}
