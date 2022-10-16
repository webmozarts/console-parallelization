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

use Error;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \Webmozarts\Console\Parallelization\Logger\StandardLogger
 *
 * @internal
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
            self::TERMINAL_WIDTH,
            new TestProgressBarFactory(),
            new ConsoleLogger($this->output),
        );
    }

    /**
     * @dataProvider configurationProvider
     */
    public function test_it_can_log_the_configuration(
        int $segmentSize,
        int $batchSize,
        int $numberOfItems,
        int $numberOfSegments,
        int $totalNumberOfBatches,
        int $numberOfProcesses,
        string $itemName,
        string $expected
    ): void {
        $this->logger->logConfiguration(
            $segmentSize,
            $batchSize,
            $numberOfItems,
            $numberOfSegments,
            $totalNumberOfBatches,
            $numberOfProcesses,
            $itemName,
        );

        self::assertSame($expected, $this->output->fetch());
    }

    public static function configurationProvider(): iterable
    {
        yield 'nominal' => [
            5,
            3,
            8,
            2,
            4,
            2,
            'tokens',
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 4 batches in 2 processes


                TXT,
        ];

        yield 'single segment' => [
            5,
            3,
            8,
            1,
            4,
            2,
            'tokens',
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 1 round, 4 batches in 2 processes


                TXT,
        ];

        yield 'single batch' => [
            5,
            3,
            8,
            2,
            1,
            2,
            'tokens',
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 1 batch in 2 processes


                TXT,
        ];

        yield 'single process' => [
            5,
            3,
            8,
            2,
            4,
            1,
            'tokens',
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 4 batches in 1 process


                TXT,
        ];
    }

    public function test_it_can_log_the_start_of_the_processing(): void
    {
        $this->logger->startProgress(10);

        $expected = <<<'TXT'
              0/10 [>---------------------------]   0%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_cannot_start_an_already_started_process(): void
    {
        $this->logger->startProgress(10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot start the progress: already started.');

        $this->logger->startProgress(10);
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

    public function test_it_cannot_advance_a_non_started_process(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the progress to be started.');

        $this->logger->advance();
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

    public function test_it_cannot_end_the_processing_of_a_non_started_process(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the progress to be started.');

        $this->logger->finish('tokens');
    }

    public function test_it_can_log_the_unexpected_output_of_a_child_process(): void
    {
        $this->logger->startProgress(10);
        $this->logger->advance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedOutput(
            'An error occurred.',
            self::ADVANCEMENT_CHARACTER,
        );

        $expected = <<<'TXT'

            ================= Process Output =================
            An error occurred.


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_removes_the_progress_character_of_the_unexpected_output_of_a_child_process(): void
    {
        $this->logger->startProgress(10);
        $this->logger->advance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedOutput(
            'An error'.self::ADVANCEMENT_CHARACTER.' occurred.',
            self::ADVANCEMENT_CHARACTER,
        );

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

        $this->logger->logCommandFinished();

        $expected = <<<'TXT'
            [debug] Command finished

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_an_item_processing_failure(): void
    {
        $this->logger->logItemProcessingFailed('item1', new Error('An error occurred.'));

        self::assertStringStartsWith(
            'Failed to process "item1": An error occurred.',
            $this->output->fetch(),
        );
    }
}
