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

use function array_keys;
use function getcwd;
use const PHP_EOL;
use PHPUnit\Framework\TestCase;
use function preg_replace;
use function str_replace;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * @coversNothing
 */
class ParallelizationIntegrationTest extends TestCase
{
    private ImportMoviesCommand $importMoviesCommand;
    private CommandTester $importMoviesCommandTester;
    private NoSubProcessCommand $noSubProcessCommand;
    private CommandTester $noSubProcessCommandTester;

    protected function setUp(): void
    {
        $this->importMoviesCommand = (new Application(new Kernel()))->add(new ImportMoviesCommand());
        $this->importMoviesCommandTester = new CommandTester($this->importMoviesCommand);

        $this->noSubProcessCommand = (new Application(new Kernel()))->add(new NoSubProcessCommand());
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
            ['command' => 'test:no-subprocess'],
            ['interactive' => true]
        );

        // TODO: note that the "in 1 process is incorrect here..."
        $expected = <<<'EOF'
            Processing 5 items in segments of 5, batches of 2, 1 round, 1 batch in 1 process

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

            Processed 5 items.

            EOF;

        $actual = $this->getOutput($commandTester);

        self::assertSame($expected, $actual);
    }

    public function test_it_uses_a_sub_process_if_only_one_process_is_used(): void
    {
        $commandTester = $this->noSubProcessCommandTester;

        $commandTester->execute(
            [
                'command' => 'test:no-subprocess',
                '--processes' => '1',
            ],
            ['interactive' => true]
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
                '--processes' => 2,
            ],
            ['interactive' => true]
        );

        $expected = <<<'EOF'
            Processing 5 movies in segments of 2, batches of 2, 3 rounds, 3 batches in 2 processes

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
             2/5 [===========>----------------]  40% 10 secs/10 secs 10.0 MiB
             4/5 [======================>-----]  80% 10 secs/10 secs 10.0 MiB
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

            Processed 5 movies.

            EOF;

        $actual = $this->getOutput($commandTester);

        self::assertSame($expected, $actual, $actual);
    }

    public function test_it_can_run_the_command_with_multiple_processes_in_debug_mode(): void
    {
        $commandTester = $this->importMoviesCommandTester;

        $commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 2,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ],
        );

        $expected = <<<'EOF'
            Processing 5 movies in segments of 2, batches of 2, 3 rounds, 3 batches in 2 processes

             0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB[debug] Command started: '/path/to/php' '/path/to/work-dir/bin/console' 'import:movies' '--child' '--env=dev'
            [debug] Command started: '/path/to/php' '/path/to/work-dir/bin/console' 'import:movies' '--child' '--env=dev'
            
             2/5 [===========>----------------]  40% 10 secs/10 secs 10.0 MiB[debug] Command finished
            [debug] Command started: '/path/to/php' '/path/to/work-dir/bin/console' 'import:movies' '--child' '--env=dev'
            
             4/5 [======================>-----]  80% 10 secs/10 secs 10.0 MiB[debug] Command finished
            
             5/5 [============================] 100% 10 secs/10 secs 10.0 MiB[debug] Command finished


            Processed 5 movies.

            EOF;

        $actual = $this->getOutput($commandTester);

        self::assertSame($expected, $actual, $actual);
    }

    private function getOutput(CommandTester $commandTester): string
    {
        $output = $commandTester->getDisplay(true);

        $output = preg_replace(
            '/\d+(\.\d+)? ([A-Z]i)?B/',
            '10.0 MiB',
            $output
        );

        $output = str_replace(
            '< 1 sec',
            '10 secs',
            $output
        );

        $output = preg_replace(
            '/\d+ secs?/',
            '10 secs',
            $output
        );

        $replaceMap = [
            '%  10 secs' => '% 10 secs',
            'secs  10.0 MiB' => 'secs 10.0 MiB',
            PHP_EOL => "\n",
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output
        );
    }
}
