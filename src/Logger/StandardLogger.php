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
use function count;
use function implode;
use function memory_get_peak_usage;
use function memory_get_usage;
use function microtime;
use function sprintf;
use function str_contains;
use function str_pad;
use function str_replace;
use const STR_PAD_BOTH;

final class StandardLogger implements Logger
{
    private const COLORS = [
        'green',
        'yellow',
        'blue',
        'magenta',
        'cyan',
        'white',
        'gray',
        'black',
        'red',
        'bright-red',
        'bright-green',
        'bright-yellow',
        'bright-blue',
        'bright-magenta',
        'bright-cyan',
        'bright-white',
    ];

    private array $started = [];
    private int $count = -1;

    private SymfonyStyle $io;
    private int $terminalWidth;
    private ProgressBar $progressBar;
    private ProgressBarFactory $progressBarFactory;
    private float $startTime;
    private bool $advanced;
    private LoggerInterface $logger;

    public function __construct(
        InputInterface $input,
        OutputInterface $output,
        int $terminalWidth,
        ProgressBarFactory $progressBarFactory
    ) {
        $this->io = new SymfonyStyle($input, $output);
        $this->terminalWidth = $terminalWidth;
        $this->progressBarFactory = $progressBarFactory;
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
        $this->advanced = true;
    }

    public function logAdvance(int $steps = 1): void
    {
        Assert::true(
            isset($this->progressBar),
            'Expected the progress to be started.',
        );

        $this->progressBar->advance($steps);
        $this->advanced = true;
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

        unset($this->progressBar);
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        if ($this->advanced) {
            $this->io->newLine();
        }

        if ($this->io->isVeryVerbose()) {
            $this->logger->notice(
                sprintf(
                    'Started process #%d (PID %s): %s',
                    $index,
                    $pid,
                    $commandName,
                ),
            );
        }

        $this->advanced = false;
        $this->started[$index] = ['border' => ++$this->count % count(self::COLORS)];
    }

    public function logChildProcessFinished(int $index): void
    {
        if ($this->advanced) {
            $this->io->newLine();
        }

        if ($this->io->isVeryVerbose()) {
            $this->logger->notice(
                sprintf(
                    'Stopped process #%d',
                    $index,
                ),
            );
        }

        $this->advanced = false;
    }

    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $buffer,
        string $progressSymbol
    ): void {
        $this->io->newLine();

        $error = str_contains($buffer, 'Failed to process the item');
        $message = str_replace($progressSymbol, '', $buffer);

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

        $this->io->writeln([
            sprintf(
                '<comment>%s</comment>',
                str_pad(
                    $sectionTitle,
                    $this->terminalWidth,
                    '=',
                    STR_PAD_BOTH,
                ),
            ),
            $this->progress(
                $index,
                $message,
                $error,
            ),
        ]);

        $this->io->newLine();
    }

    private function progress(int $id, string $buffer, bool $error = false, string $prefix = 'OUT', string $errorPrefix = 'ERR'): string
    {
        $message = '';

        if ($error) {
            if (isset($this->started[$id]['out'])) {
                $message .= "\n";
                unset($this->started[$id]['out']);
            }
            if (!isset($this->started[$id]['err'])) {
                $message .= sprintf('%s<bg=red;fg=white> %s </> ', $this->getBorder($id), $errorPrefix);
                $this->started[$id]['err'] = true;
            }

            $message .= str_replace("\n", sprintf("\n%s<bg=red;fg=white> %s </> ", $this->getBorder($id), $errorPrefix), $buffer);
        } else {
            if (isset($this->started[$id]['err'])) {
                $message .= "\n";
                unset($this->started[$id]['err']);
            }
            if (!isset($this->started[$id]['out'])) {
                $message .= sprintf('%s<bg=green;fg=white> %s </> ', $this->getBorder($id), $prefix);
                $this->started[$id]['out'] = true;
            }

            $message .= str_replace("\n", sprintf("\n%s<bg=green;fg=white> %s </> ", $this->getBorder($id), $prefix), $buffer);
        }

        return $message;
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

    private function getBorder(int $id): string
    {
        return '';

        return sprintf('<bg=%s> </>', self::COLORS[$this->started[$id]['border']]);
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
}
