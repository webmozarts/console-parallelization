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

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Configuration;
use function array_filter;
use function implode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function sprintf;
use function str_replace;
use const STR_PAD_BOTH;

final class StandardLogger implements Logger
{
    private readonly SymfonyStyle $io;
    private ProgressBar $progressBar;
    private float $startTime;
    private ?string $lastCall = null;
    private readonly LoggerInterface $logger;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        private readonly int $terminalWidth,
        private readonly ProgressBarFactory $progressBarFactory
    ) {
        $this->io = new SymfonyStyle($input, $output);
        $this->logger = new ConsoleLogger($output);
    }

    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        $numberOfItems ??= '???';
        $segmentSize = $configuration->getSegmentSize();
        $numberOfSegments = $configuration->getNumberOfSegments();
        $totalNumberOfBatches = $configuration->getTotalNumberOfBatches();
        $numberOfProcesses = $configuration->getNumberOfProcesses();

        if ($shouldSpawnChildProcesses) {
            $parts = [
                sprintf(
                    'Processing %s %s in segments of %d',
                    $numberOfItems,
                    $itemName,
                    $segmentSize,
                ),
                sprintf(
                    'batches of %d',
                    $batchSize,
                ),
                self::enunciate('round', $numberOfSegments),
                self::enunciate('batch', $totalNumberOfBatches),
            ];

            $message = sprintf(
                '%s, with %d %s.',
                implode(', ', array_filter($parts)),
                $numberOfProcesses,
                Inflector::pluralize('child process', $numberOfProcesses),
            );
        } else {
            $parts = [
                sprintf(
                    'Processing %s %s',
                    $numberOfItems,
                    $itemName,
                ),
                sprintf(
                    'batches of %d',
                    $batchSize,
                ),
                self::enunciate('batch', $totalNumberOfBatches),
            ];

            $message = sprintf(
                '%s, in the current process.',
                implode(', ', array_filter($parts)),
            );
        }

        $this->io->writeln($message);
        $this->io->newLine();
    }

    public function logStart(?int $numberOfItems): void
    {
        Assert::false(
            isset($this->progressBar),
            'Cannot start the progress: already started.',
        );

        $this->startTime = microtime(true);
        $this->progressBar = $this->progressBarFactory->create(
            $this->io,
            $numberOfItems ?? 0,
        );
        $this->lastCall = 'logStart';
    }

    public function logAdvance(int $steps = 1): void
    {
        Assert::true(
            isset($this->progressBar),
            'Expected the progress to be started.',
        );

        $this->progressBar->advance($steps);
        $this->lastCall = 'logAdvance';
    }

    public function logFinish(string $itemName): void
    {
        Assert::true(
            isset($this->progressBar),
            'Expected the progress to be started.',
        );

        $this->progressBar->finish();

        $this->io->comment(
            sprintf(
                '<info>Memory usage: %s (peak: %s), time: %s</info>',
                MemorySizeFormatter::format(memory_get_usage()),
                MemorySizeFormatter::format(memory_get_peak_usage()),
                Helper::formatTime(microtime(true) - $this->startTime),
            ),
        );

        $this->io->writeln(sprintf(
            'Processed %d %s.',
            $this->progressBar->getMaxSteps(),
            $itemName,
        ));

        unset($this->progressBar, $this->lastCall);
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        if (!$this->io->isVeryVerbose()) {
            return;
        }

        if ('logAdvance' === $this->lastCall) {
            $this->io->newLine();
        }

        $this->logger->notice(
            sprintf(
                'Started process #%d (PID %s): %s',
                $index,
                $pid,
                $commandName,
            ),
        );

        $this->lastCall = 'logChildProcessStarted';
    }

    public function logChildProcessFinished(int $index): void
    {
        if (!$this->io->isVeryVerbose()) {
            return;
        }

        if ('logAdvance' === $this->lastCall) {
            $this->io->newLine();
        }

        $this->logger->notice(
            sprintf(
                'Stopped process #%d',
                $index,
            ),
        );

        $this->lastCall = 'logChildProcessFinished';
    }

    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $type,
        string $buffer,
        string $progressSymbol
    ): void {
        $error = 'err' === $type;
        $message = str_replace($progressSymbol, '', $buffer);

        if ($this->lastCall !== 'logUnexpectedChildProcessOutput:'.$index) {
            $this->io->newLine();
            $this->logUnexpectedChildProcessOutputSection($index, $pid);
        }

        $this->io->writeln(
            self::formatBuffer(
                $message,
                $error,
            ),
        );

        $this->io->newLine();

        $this->lastCall = 'logUnexpectedChildProcessOutput:'.$index;
    }

    private function logUnexpectedChildProcessOutputSection(int $index, ?int $pid): void
    {
        $pidPart = null !== $pid
            ? sprintf(
                ' (PID %s)',
                $pid,
            )
            : '';

        $sectionTitle = sprintf(
            ' Process #%d%s Output ',
            $index,
            $pidPart,
        );

        $processSectionLine = sprintf(
            '<comment>%s</comment>',
            mb_str_pad(
                $sectionTitle,
                $this->terminalWidth,
                '=',
                STR_PAD_BOTH,
            ),
        );

        $this->io->writeln($processSectionLine);
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        $this->io->writeln(sprintf(
            "Failed to process the item \"%s\": %s\n%s",
            $item,
            $throwable->getMessage(),
            $throwable->getTraceAsString(),
        ));
    }

    /**
     * @param positive-int|0 $count
     */
    private static function enunciate(
        string $singular,
        ?int $count
    ): ?string {
        return null === $count
            ? null
            : sprintf(
                '%d %s',
                $count,
                Inflector::pluralize($singular, $count),
            );
    }

    private static function formatBuffer(
        string $buffer,
        bool $error
    ): string {
        if ($error) {
            $message = '<bg=red;fg=white> ERR </> ';
            $message .= str_replace(
                "\n",
                "\n<bg=red;fg=white> ERR </> ",
                $buffer,
            );
        } else {
            $message = '<bg=green;fg=white> OUT </> ';
            $message .= str_replace(
                "\n",
                "\n<bg=green;fg=white> OUT </> ",
                $buffer,
            );
        }

        return $message;
    }
}
