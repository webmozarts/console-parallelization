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
    /**
     * @var ImportMoviesCommand
     */
    private $command;

    /**
     * @var CommandTester
     */
    private $commandTester;

    protected function setUp(): void
    {
        $this->command = (new Application(new Kernel()))->add(new ImportMoviesCommand());

        $this->commandTester = new CommandTester($this->command);
    }

    public function test_it_can_run_the_command_without_sub_processes(): void
    {
        $this->commandTester->execute(
            ['command' => 'import:movies'],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        self::assertSame(
            <<<'EOF'
                Processing 2 movies in segments of 2, batches of 50, 1 round, 1 batches in 1 process

                 0/2 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
                 2/2 [============================] 100% 10 secs/10 secs 10.0 MiB

                Processed 2 movies.

                EOF
            ,
            $actual,
            'Expected logs to be identical'
        );
    }

    public function test_it_does_not_use_a_sub_process_if_only_one_process_is_allowed(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 1,
            ],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        self::assertSame(
            <<<'EOF'
                Processing 2 movies in segments of 2, batches of 50, 1 round, 1 batches in 1 process

                 0/2 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
                 2/2 [============================] 100% 10 secs/10 secs 10.0 MiB

                Processed 2 movies.

                EOF
            ,
            $actual,
            'Expected logs to be identical'
        );
    }

    public function test_it_can_run_the_command_with_multiple_processes(): void
    {
        $this->command->setItems([
            'item0',
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
            'item6',
            'item7',
            'item8',
            'item9',
            'item10',
        ]);
        $this->command->setSegmentSize(2);

        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 2,
            ],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        self::assertSame(
            <<<'EOF'
                Processing 11 movies in segments of 2, batches of 2, 6 rounds, 6 batches in 2 processes

                  0/11 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB
                  6/11 [===============>------------]  54% 10 secs/10 secs 10.0 MiB
                 11/11 [============================] 100% 10 secs/10 secs 10.0 MiB

                Processed 11 movies.

                EOF
            ,
            $actual,
            'Expected logs to be identical'
        );
    }

    public function test_it_can_run_the_command_with_multiple_processes_in_debug_mode(): void
    {
        $this->command->setItems([
            'item0',
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
            'item6',
            'item7',
            'item8',
            'item9',
            'item10',
        ]);
        $this->command->setSegmentSize(2);

        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 2,
            ],
            [
                'interactive' => true,
                'verbosity' => OutputInterface::VERBOSITY_DEBUG,
            ]
        );

        $actual = $this->getOutput();

        self::assertSame(
            <<<'EOF'
                Processing 11 movies in segments of 2, batches of 2, 6 rounds, 6 batches in 2 processes

                  0/11 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB[debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev
                [debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev
                [debug] Command finished
                [debug] Command finished
                [debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev
                [debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev

                  6/11 [===============>------------]  54% 10 secs/10 secs 10.0 MiB[debug] Command finished
                [debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev
                [debug] Command finished
                [debug] Command started: /path/to/php /path/to/work-dir/bin/console import:movies --child --env=dev

                 11/11 [============================] 100% 10 secs/10 secs 10.0 MiB[debug] Command finished
                [debug] Command finished


                Processed 11 movies.

                EOF
            ,
            $actual,
            'Expected logs to be identical'
        );
    }

    private function getOutput(): string
    {
        $output = $this->commandTester->getDisplay(true);

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
