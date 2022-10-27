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
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Configuration;
use function sprintf;
use function str_pad;
use function str_replace;
use const STR_PAD_BOTH;

final class StandardLogger implements Logger
{
    private OutputInterface $output;
    private int $terminalWidth;
    private ProgressBar $progressBar;
    private ProgressBarFactory $progressBarFactory;
    private LoggerInterface $logger;

    public function __construct(
        OutputInterface $output,
        int $terminalWidth,
        ProgressBarFactory $progressBarFactory,
        LoggerInterface $logger
    ) {
        $this->output = $output;
        $this->terminalWidth = $terminalWidth;
        $this->progressBarFactory = $progressBarFactory;
        $this->logger = $logger;
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
        $numberOfSegments = $configuration->getNumberOfSegments() ?? '???';
        $totalNumberOfBatches = $configuration->getTotalNumberOfBatches() ?? '???';
        $numberOfProcesses = $configuration->getNumberOfProcesses();

        if ($shouldSpawnChildProcesses) {
            $this->output->writeln(sprintf(
                'Processing %s %s in segments of %d, batches of %d, %s %s, %s %s in %d %s',
                $numberOfItems,
                $itemName,
                $segmentSize,
                $batchSize,
                $numberOfSegments,
                1 === $numberOfSegments ? 'round' : 'rounds',
                $totalNumberOfBatches,
                1 === $totalNumberOfBatches ? 'batch' : 'batches',
                $numberOfProcesses,
                1 === $numberOfProcesses ? 'process' : 'processes',
            ));
        } else {
            $this->output->writeln(sprintf(
                'Processing %s %s, batches of %d, %s %s',
                $numberOfItems,
                $itemName,
                $batchSize,
                $totalNumberOfBatches,
                1 === $totalNumberOfBatches ? 'batch' : 'batches',
            ));
        }

        $this->output->writeln('');
    }

    public function logStart(?int $numberOfItems): void
    {
        Assert::false(
            isset($this->progressBar),
            'Cannot start the progress: already started.',
        );

        $this->progressBar = $this->progressBarFactory->create(
            $this->output,
            $numberOfItems ?? 0,
        );
    }

    public function logAdvance(int $steps = 1): void
    {
        Assert::true(
            isset($this->progressBar),
            'Expected the progress to be started.',
        );

        $this->progressBar->advance($steps);
    }

    public function logFinish(string $itemName): void
    {
        Assert::true(
            isset($this->progressBar),
            'Expected the progress to be started.',
        );

        $this->progressBar->finish();

        $this->output->writeln('');
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            'Processed %d %s.',
            $this->progressBar->getMaxSteps(),
            $itemName,
        ));

        unset($this->progressBar);
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        $this->output->writeln(sprintf(
            "Failed to process \"%s\": %s\n%s",
            $item,
            $throwable->getMessage(),
            $throwable->getTraceAsString(),
        ));
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        $this->logger->debug('Command started: '.$commandName);
    }

    public function logChildProcessFinished(int $index): void
    {
        $this->logger->debug('Command finished');
    }

    public function logUnexpectedChildProcessOutput(int $index, ?int $pid, string $buffer, string $progressSymbol): void
    {
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            '<comment>%s</comment>',
            str_pad(
                ' Process Output ',
                $this->terminalWidth,
                '=',
                STR_PAD_BOTH,
            ),
        ));
        $this->output->writeln(str_replace($progressSymbol, '', $buffer));
        $this->output->writeln('');
    }
}
