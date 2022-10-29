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
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\Configuration;
use Webmozarts\Console\Parallelization\Integration\OutputNormalizer;
use Webmozarts\Console\Parallelization\PHPUnitProviderUtil;

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
            new StringInput(''),
            $this->output,
            self::TERMINAL_WIDTH,
            new TestProgressBarFactory(),
        );
    }

    /**
     * @dataProvider configurationProvider
     */
    public function test_it_can_log_the_configuration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses,
        string $expected
    ): void {
        $this->logger->logConfiguration(
            $configuration,
            $batchSize,
            $numberOfItems,
            $itemName,
            $shouldSpawnChildProcesses,
        );

        self::assertSame($expected, $this->output->fetch());
    }

    public static function configurationProvider(): iterable
    {
        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[without child processes] ',
            self::withoutChildConfigurationProvider(),
        );

        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[with child process(es)] ',
            self::withChildConfigurationProvider(),
        );
    }

    private static function withoutChildConfigurationProvider(): iterable
    {
        yield 'nominal' => [
            new Configuration(
                2,
                5,
                8,
                2,
            ),
            3,
            4,
            'tokens',
            false,
            <<<'TXT'
                Processing 4 tokens, batches of 3, 2 batches, in the current process.


                TXT,
        ];

        yield 'single batch' => [
            new Configuration(
                2,
                5,
                2,
                1,
            ),
            3,
            8,
            'tokens',
            false,
            <<<'TXT'
                Processing 8 tokens, batches of 3, 1 batch, in the current process.


                TXT,
        ];

        yield 'single process' => [
            new Configuration(
                1,
                5,
                2,
                4,
            ),
            3,
            8,
            'tokens',
            false,
            <<<'TXT'
                Processing 8 tokens, batches of 3, 4 batches, in the current process.


                TXT,
        ];

        yield 'multiple process' => [
            new Configuration(
                1,
                5,
                2,
                4,
            ),
            3,
            8,
            'tokens',
            false,
            <<<'TXT'
                Processing 8 tokens, batches of 3, 4 batches, in the current process.


                TXT,
        ];

        yield 'unknown number of batches' => [
            new Configuration(
                1,
                5,
                2,
                null,
            ),
            3,
            8,
            'tokens',
            false,
            <<<'TXT'
                Processing 8 tokens, batches of 3, in the current process.


                TXT,
        ];

        yield 'unknown number of items' => [
            new Configuration(
                1,
                5,
                2,
                4,
            ),
            3,
            null,
            'tokens',
            false,
            <<<'TXT'
                Processing ??? tokens, batches of 3, 4 batches, in the current process.


                TXT,
        ];

        yield 'unknown number of rounds' => [
            new Configuration(
                1,
                5,
                null,
                4,
            ),
            3,
            8,
            'tokens',
            false,
            <<<'TXT'
                Processing 8 tokens, batches of 3, 4 batches, in the current process.


                TXT,
        ];
    }

    private static function withChildConfigurationProvider(): iterable
    {
        yield 'nominal' => [
            new Configuration(
                2,
                5,
                2,
                4,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 4 batches, with 2 parallel child processes.


                TXT,
        ];

        yield 'single segment' => [
            new Configuration(
                2,
                5,
                1,
                4,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 1 round, 4 batches, with 2 parallel child processes.


                TXT,
        ];

        yield 'single batch' => [
            new Configuration(
                2,
                5,
                2,
                1,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 1 batch, with 2 parallel child processes.


                TXT,
        ];

        yield 'single process' => [
            new Configuration(
                1,
                5,
                2,
                4,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, 4 batches, with 1 child process.


                TXT,
        ];

        yield 'unknown number of batches' => [
            new Configuration(
                1,
                5,
                2,
                null,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 2 rounds, with 1 child process.


                TXT,
        ];

        yield 'unknown number of items' => [
            new Configuration(
                1,
                5,
                2,
                4,
            ),
            3,
            null,
            'token(s)',
            true,
            <<<'TXT'
                Processing ??? token(s) in segments of 5, batches of 3, 2 rounds, 4 batches, with 1 child process.


                TXT,
        ];

        yield 'unknown number of rounds' => [
            new Configuration(
                1,
                5,
                null,
                4,
            ),
            3,
            8,
            'tokens',
            true,
            <<<'TXT'
                Processing 8 tokens in segments of 5, batches of 3, 4 batches, with 1 child process.


                TXT,
        ];
    }

    public function test_it_can_log_the_start_of_the_processing(): void
    {
        $this->logger->logStart(10);

        $expected = <<<'TXT'
              0/10 [>---------------------------]   0%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_cannot_start_an_already_started_process(): void
    {
        $this->logger->logStart(10);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot start the progress: already started.');

        $this->logger->logStart(10);
    }

    public function test_it_can_log_the_progress_of_the_processing(): void
    {
        $this->logger->logStart(10);
        $this->output->fetch();

        $this->logger->logAdvance();

        $expected = <<<'TXT'

              1/10 [==>-------------------------]  10%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_cannot_advance_a_non_started_process(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the progress to be started.');

        $this->logger->logAdvance();
    }

    public function test_it_can_log_the_progress_of_the_processing_of_multiple_items(): void
    {
        $this->logger->logStart(10);
        $this->output->fetch();

        $this->logger->logAdvance(4);

        $expected = <<<'TXT'

              4/10 [===========>----------------]  40%
            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_end_of_the_processing(): void
    {
        $this->logger->logStart(10);
        $this->output->fetch();

        $this->logger->logFinish('tokens');

        $expected = <<<'TXT'

             10/10 [============================] 100%

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 10 tokens.

            TXT;

        $actual = OutputNormalizer::removeTrailingSpaces(
            OutputNormalizer::normalizeMemoryUsage(
                OutputNormalizer::normalizeProgressBarTimeTaken(
                    $this->output->fetch(),
                ),
            ),
        );

        self::assertSame($expected, $actual);
    }

    public function test_it_cannot_end_the_processing_of_a_non_started_process(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the progress to be started.');

        $this->logger->logFinish('tokens');
    }

    public function test_it_can_log_the_unexpected_output_of_a_child_process(): void
    {
        $this->logger->logStart(10);
        $this->logger->logAdvance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedChildProcessOutput(
            2,
            23123,
            'An error occurred.',
            self::ADVANCEMENT_CHARACTER,
        );

        $expected = <<<'TXT'

            ========= Process #2 (PID 23123) Output ==========
             OUT  An error occurred.


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_unexpected_output_of_a_stopped_child_process(): void
    {
        $this->logger->logStart(10);
        $this->logger->logAdvance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedChildProcessOutput(
            2,
            null,
            'An error occurred.',
            self::ADVANCEMENT_CHARACTER,
        );

        $expected = <<<'TXT'

            =============== Process #2 Output ================
             OUT  An error occurred.


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_removes_the_progress_character_of_the_unexpected_output_of_a_child_process(): void
    {
        $this->logger->logStart(10);
        $this->logger->logAdvance(4);
        $this->output->fetch();

        $this->logger->logUnexpectedChildProcessOutput(
            23,
            23132,
            'An error'.self::ADVANCEMENT_CHARACTER.' occurred.',
            self::ADVANCEMENT_CHARACTER,
        );

        $expected = <<<'TXT'

            ========= Process #23 (PID 23132) Output =========
             OUT  An error occurred.


            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_start_of_a_command(): void
    {
        $this->logger->logStart(null);
        $this->logger->logChildProcessStarted(
            2,
            2132,
            '/path/to/bin/console foo:bar --child',
        );
        $this->output->fetch();

        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logChildProcessStarted(
            2,
            2132,
            '/path/to/bin/console foo:bar --child',
        );

        $expected = <<<'TXT'
            [notice] Started process #2 (PID 2132): /path/to/bin/console foo:bar --child

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_start_of_a_command_in_the_middle_of_some_progress(): void
    {
        $this->logger->logStart(null);
        $this->logger->logAdvance(2);
        $this->output->fetch();

        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logChildProcessStarted(
            2,
            2132,
            '/path/to/bin/console foo:bar --child',
        );

        $expected = <<<'TXT'

            [notice] Started process #2 (PID 2132): /path/to/bin/console foo:bar --child

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_end_of_a_command(): void
    {
        $this->logger->logStart(null);
        $this->logger->logAdvance(2);
        $this->logger->logChildProcessFinished(2);
        $this->output->fetch();

        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logChildProcessFinished(3);

        $expected = <<<'TXT'
            [notice] Stopped process #3

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_the_end_of_a_command_in_the_middle_of_a_progress(): void
    {
        $this->logger->logStart(null);
        $this->logger->logAdvance(2);
        $this->output->fetch();

        $this->output->setVerbosity(OutputInterface::VERBOSITY_DEBUG);

        $this->logger->logChildProcessFinished(3);

        $expected = <<<'TXT'

            [notice] Stopped process #3

            TXT;

        self::assertSame($expected, $this->output->fetch());
    }

    public function test_it_can_log_an_item_processing_failure(): void
    {
        $this->logger->logItemProcessingFailed(
            'item1',
            new Error('An error occurred.'),
        );

        self::assertStringStartsWith(
            'Failed to process the item "item1": An error occurred.',
            $this->output->fetch(),
        );
    }
}
