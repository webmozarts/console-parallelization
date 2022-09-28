<?php

/*
 * This file is part of the Fidry\Console package.
 *
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use PHPUnit\Framework\TestCase;
use function preg_replace;
use function str_replace;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Kernel;

/**
 * @coversNothing
 */
class ParallelizationIntegrationTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var CommandTester
     */
    private $commandTester;

    protected function setUp(): void
    {
        $command = new ImportMoviesCommand();

        $this->application = new Application(
            new class('dev', true) extends Kernel {
                public function registerBundles(): array
                {
                    return [];
                }

                public function registerContainerConfiguration(LoaderInterface $loader): void
                {
                }
            }
        );
        $this->application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function test_it_can_run_the_command_without_sub_processes(): void
    {
        $this->commandTester->execute(
            ['command' => 'import:movies'],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        if ($this->isSymfony3()) {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 2, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 1/2 [==============>-------------]  50% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        } else {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 2, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        }
    }

    public function test_it_can_run_the_command_with_a_single_sub_processes(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 1,
            ],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        if ($this->isSymfony3()) {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 1/2 [==============>-------------]  50% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        } else {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        }
    }

    public function test_it_can_run_the_command_with_multiple_processes(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 2,
            ],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        if ($this->isSymfony3()) {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 2 processes

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 1/2 [==============>-------------]  50% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        } else {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 2 processes

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        }
    }

    public function test_it_can_run_the_command_with_one_process_as_child_process(): void
    {
        $this->commandTester->execute(
            [
                'command' => 'import:movies',
                '--processes' => 1,
            ],
            ['interactive' => true]
        );

        $actual = $this->getOutput();

        if ($this->isSymfony3()) {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 1/2 [==============>-------------]  50% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        } else {
            self::assertSame(
                <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 round, 1 batch in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 10.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 10.0 MiB

Processed 2 movies.

EOF
                ,
                $actual,
                'Expected logs to be identical'
            );
        }
    }

    private function getOutput(): string
    {
        $output = $this->commandTester->getDisplay(true);

        $output = preg_replace(
            '/\d+(\.\d+)? ([A-Z]i)?B/',
            '10.0 MiB',
            $output
        );

        return str_replace(PHP_EOL, "\n", $output);
    }

    private function isSymfony3(): bool
    {
        return Kernel::VERSION_ID < 40000;
    }
}
