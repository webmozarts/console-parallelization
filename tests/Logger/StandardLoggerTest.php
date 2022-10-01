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

namespace Webmozarts\Console\Parallelization\Logger;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \Webmozarts\Console\Parallelization\Logger\StandardLogger
 */
final class StandardLoggerTest extends TestCase
{
    private const ADVANCEMENT_CHARACTER = '▌';
    private const TERMINAL_WIDTH = 50;

    private BufferedOutput $output;
    private StandardLogger $logger;

    protected function setUp(): void
    {
        $this->output = new BufferedOutput();

        $this->logger = new StandardLogger(
            $this->output,
            self::ADVANCEMENT_CHARACTER,
            self::TERMINAL_WIDTH,
            new TestProgressBarFactory(),
            new ConsoleLogger($this->output),
        );
    }

    public function test_it_can_log_the_configuration(): void
    {
        $this->logger->logConfiguration(
            5,
            3,
            8,
            2,
            4,
            2,
            'token',
        );

        $expected = <<<'TXT'
            Processing 8 token in segments of 5, batches of 3, 2 rounds, 4 batches in 2 processes


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_start_of_the_processing(): void
    {
        $this->logger->startProgress(10);

        $expected = <<<'TXT'
              0/10 [>---------------------------]   0%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_progress_of_the_processing(): void
    {
        $this->logger->startProgress(10);
        $this->output->fetch();

        $this->logger->advance();

        $expected = <<<'TXT'
            
              1/10 [==>-------------------------]  10%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_progress_of_the_processing_of_multiple_items(): void
    {
        $this->logger->startProgress(10);
        $this->output->fetch();

        $this->logger->advance(4);

        $expected = <<<'TXT'
            
              4/10 [===========>----------------]  40%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_end_of_the_processing(): void
    {
        $this->logger->startProgress(10);
        $this->output->fetch();

        $this->logger->finish('tokens');

        $expected = <<<'TXT'
            
             10/10 [============================] 100%

            Processed 10 tokens.

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_unexpected_output_of_a_child_process(): void
    {
        $this->logger->startProgress(10);
        $this->logger->advance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedOutput('An error occurred.');

        $expected = <<<'TXT'
            
            ================= Process Output =================
            An error occurred.


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_start_of_a_command(): void
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logCommandStarted('/path/to/bin/console foo:bar --child');

        $expected = <<<'TXT'
            [debug] Command started: /path/to/bin/console foo:bar --child

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_end_of_a_command(): void
    {
        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logCommandFinished('/path/to/bin/console foo:bar --child');

        $expected = <<<'TXT'
            [debug] Command finished: /path/to/bin/console foo:bar --child

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }
}
