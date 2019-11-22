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

use function feof;
use function fgets;
use PHPUnit\Framework\TestCase;
use function preg_replace;
use function rewind;
use function str_replace;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Output\StreamOutput;
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

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $command = new ImportMoviesCommand();

        $this->application = new Application(
            new class('dev', true) extends Kernel {
                /**
                 * {@inheritdoc}
                 */
                public function registerBundles(): array
                {
                    return [];
                }

                /**
                 * {@inheritdoc}
                 */
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

        $actual = $this->commandTester->getDisplay(true);

        $this->assertSame(
            <<<'EOF'
Processing 2 movies in segments of 2, batches of 50, 1 round, 1 batches in 1 process

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 8.0 MiB

Processed 2 movies.

EOF
            ,
            $actual,
            'Expected logs to be identical'
        );
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

        $actual = $this->commandTester->getDisplay(true);

        $this->assertSame(
        <<<'EOF'
Processing 2 movies in segments of 50, batches of 50, 1 rounds, 1 batches in 2 processes

 0/2 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
 2/2 [============================] 100% < 1 sec/< 1 sec 8.0 MiB

Processed 2 movies.

EOF
        ,
        $actual,
        'Expected logs to be identical'
    );
    }

    /**
     * Returns the output for the tester.
     */
    protected function getOutput(CommandTester $tester): string
    {
        /** @var StreamOutput $output */
        $output = $tester->getOutput();
        $stream = $output->getStream();
        $string = '';

        rewind($stream);

        while (false === feof($stream)) {
            $string .= fgets($stream);
        }

        $string = preg_replace(
            [
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/',
            ],
            ['', '', ''],
            $string
        );

        return str_replace(PHP_EOL, "\n", $string);
    }
}
