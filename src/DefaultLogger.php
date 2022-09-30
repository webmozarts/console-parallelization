<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozart\Assert\Assert;
use function sprintf;
use function str_pad;
use function str_replace;
use const STR_PAD_BOTH;

final class DefaultLogger implements Logger
{
    private OutputInterface $output;
    private string $advancementChar;
    private int $terminalWidth;
    private ProgressBar $progressBar;

    public function __construct(
        OutputInterface $output,
        string $advancementChar,
        int $terminalWidth
    ) {
        $this->output = $output;
        $this->advancementChar = $advancementChar;
        $this->terminalWidth = $terminalWidth;
    }

    public function logConfiguration(
        int $segmentSize,
        int $batchSize,
        int $numberOfItems,
        int $numberOfSegments,
        int $numberOfBatches,
        int $numberOfProcesses,
        string $itemName
    ): void {
        $this->output->writeln(sprintf(
            'Processing %d %s in segments of %d, batches of %d, %d %s, %d %s in %d %s',
            $numberOfItems,
            $itemName,
            $segmentSize,
            $batchSize,
            $numberOfSegments,
            1 === $numberOfSegments ? 'round' : 'rounds',
            $numberOfBatches,
            1 === $numberOfBatches ? 'batch' : 'batches',
            $numberOfProcesses,
            1 === $numberOfProcesses ? 'process' : 'processes',
        ));
        $this->output->writeln('');
    }

    public function startProgress(int $numberOfItems): void
    {
        Assert::false(
            isset($this->progressBar),
            'Cannot start the progress: already started.',
        );

        $progressBar = new ProgressBar($this->output, $numberOfItems);
        $progressBar->setFormat('debug');
        $progressBar->start();

        $this->progressBar = $progressBar;
    }

    public function advance(int $steps = 1): void
    {
        Assert::notNull(
            $this->progressBar,
            'Expected the progress to be started.',
        );

        $this->progressBar->advance($steps);
    }

    public function finish(string $itemName): void
    {
        $progressBar = $this->progressBar;
        Assert::notNull(
            $progressBar,
            'Expected the progress to be started.',
        );

        $progressBar->finish();

        $this->output->writeln('');
        $this->output->writeln('');
        $this->output->writeln(sprintf(
            'Processed %d %s.',
            $progressBar->getMaxSteps(),
            $itemName,
        ));

        unset($this->progressBar);
    }

    public function logUnexpectedOutput(string $buffer): void
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
        $this->output->writeln(str_replace($this->advancementChar, '', $buffer));
        $this->output->writeln('');
    }
}
