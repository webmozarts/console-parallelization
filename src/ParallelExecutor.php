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

use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Input\ChildCommandFactory;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Process\ProcessLauncher;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use function mb_strlen;
use function sprintf;

final class ParallelExecutor
{
    /**
     * @var callable(InputInterface):iterable<string>
     */
    private $fetchItems;

    /**
     * @var callable(string, InputInterface, OutputInterface):void
     */
    private $runSingleCommand;

    /**
     * @var callable(positive-int|0|null): string
     */
    private $getItemName;

    private ErrorHandler $errorHandler;

    /**
     * @var resource
     */
    private $childSourceStream;

    /**
     * @var positive-int
     */
    private int $batchSize;

    /**
     * @var positive-int
     */
    private int $segmentSize;

    /**
     * @var callable(InputInterface, OutputInterface):void
     */
    private $runBeforeFirstCommand;

    /**
     * @var callable(InputInterface, OutputInterface):void
     */
    private $runAfterLastCommand;

    /**
     * @var callable(InputInterface, OutputInterface, list<string>):void
     */
    private $runBeforeBatch;

    /**
     * @var callable(InputInterface, OutputInterface, list<string>):void
     */
    private $runAfterBatch;

    private string $progressSymbol;

    private ChildCommandFactory $childCommandFactory;

    private string $workingDirectory;

    /**
     * @var array<string, string>|null
     */
    private ?array $extraEnvironmentVariables;

    private ProcessLauncherFactory $processLauncherFactory;

    /**
     * @var callable(): void
     */
    private $processTick;

    /**
     * @internal The ParallelExecutor should only be created via its factory
     *           ParallelExecutorFactory. This method signature is not subject
     *           to the BC policy.
     *
     * @param callable(InputInterface):iterable<string>                    $fetchItems
     * @param callable(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param callable(positive-int|0|null):string                         $getItemName
     * @param resource                                                     $childSourceStream
     * @param positive-int                                                 $batchSize
     * @param positive-int                                                 $segmentSize
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param array<string, string>                                        $extraEnvironmentVariables
     * @param callable(): void                                             $processTick
     */
    public function __construct(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        ErrorHandler $errorHandler,
        $childSourceStream,
        int $batchSize,
        int $segmentSize,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        string $progressSymbol,
        ChildCommandFactory $childCommandFactory,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        ProcessLauncherFactory $processLauncherFactory,
        callable $processTick
    ) {
        self::validateSegmentSize($segmentSize);
        self::validateBatchSize($batchSize);
        self::validateProgressSymbol($progressSymbol);

        $this->fetchItems = $fetchItems;
        $this->runSingleCommand = $runSingleCommand;
        $this->getItemName = $getItemName;
        $this->errorHandler = $errorHandler;
        $this->childSourceStream = $childSourceStream;
        $this->batchSize = $batchSize;
        $this->segmentSize = $segmentSize;
        $this->runBeforeFirstCommand = $runBeforeFirstCommand;
        $this->runAfterLastCommand = $runAfterLastCommand;
        $this->runBeforeBatch = $runBeforeBatch;
        $this->runAfterBatch = $runAfterBatch;
        $this->progressSymbol = $progressSymbol;
        $this->childCommandFactory = $childCommandFactory;
        $this->workingDirectory = $workingDirectory;
        $this->extraEnvironmentVariables = $extraEnvironmentVariables;
        $this->processLauncherFactory = $processLauncherFactory;
        $this->processTick = $processTick;
    }

    /**
     * @return 0|positive-int
     */
    public function execute(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        if ($parallelizationInput->isChildProcess()) {
            return $this->executeChildProcess($input, $output, $logger);
        }

        return $this->executeMainProcess(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );
    }

    /**
     * Executes the main process.
     *
     * The main process spawns as many child processes as set in the
     * "--processes" option. Each of the child processes receives a segment of
     * items of the processed data set and terminates. As long as there is data
     * left to process, new child processes are spawned automatically.
     *
     * @return 0|positive-int
     */
    private function executeMainProcess(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        ($this->runBeforeFirstCommand)($input, $output);

        $batchSize = $this->batchSize;
        $desiredSegmentSize = $this->segmentSize;

        $itemIterator = ChunkedItemsIterator::fromItemOrCallable(
            $parallelizationInput->getItem(),
            fn () => ($this->fetchItems)($input),
            $batchSize,
        );

        $numberOfItems = $itemIterator->getNumberOfItems();

        $shouldSpawnChildProcesses = !$parallelizationInput->shouldBeProcessedInMainProcess();

        $configuration = Configuration::create(
            $shouldSpawnChildProcesses,
            $numberOfItems,
            $parallelizationInput->getNumberOfProcesses(),
            $desiredSegmentSize,
            $batchSize,
        );

        $numberOfProcesses = $configuration->getNumberOfProcesses();
        $segmentSize = $configuration->getSegmentSize();
        $itemName = ($this->getItemName)($numberOfItems);

        $logger->logConfiguration(
            $configuration,
            $batchSize,
            $numberOfItems,
            $itemName,
            $shouldSpawnChildProcesses,
        );

        $logger->startProgress($numberOfItems);

        if ($shouldSpawnChildProcesses) {
            $exitCode = $this
                ->createProcessLauncher(
                    $segmentSize,
                    $numberOfProcesses,
                    $input,
                    $logger,
                )
                ->run($itemIterator->getItems());
        } else {
            $exitCode = $this->processItems(
                $itemIterator,
                $input,
                $output,
                $logger,
                static fn () => $logger->advance(),
            );
        }

        $logger->finish($itemName);

        ($this->runAfterLastCommand)($input, $output);

        return $exitCode;
    }

    /**
     * Executes the child process.
     *
     * This method reads the items from the standard input that the main process
     * piped into the process. These items are passed to runSingleCommand() one
     * by one.
     *
     * @return 0|positive-int
     */
    private function executeChildProcess(
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        $itemIterator = ChunkedItemsIterator::fromStream(
            $this->childSourceStream,
            $this->batchSize,
        );

        $progressSymbol = $this->progressSymbol;

        return $this->processItems(
            $itemIterator,
            $input,
            $output,
            $logger,
            static fn () => $output->write($progressSymbol),
        );
    }

    /**
     * @param callable():void $advance
     *
     * @return 0|positive-int
     */
    private function processItems(
        ChunkedItemsIterator $itemIterator,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger,
        callable $advance
    ): int {
        $exitCode = 0;

        foreach ($itemIterator->getItemChunks() as $items) {
            ($this->runBeforeBatch)($input, $output, $items);

            foreach ($items as $item) {
                $exitCode += $this->runTolerantSingleCommand($item, $input, $output, $logger);

                $advance();
            }

            ($this->runAfterBatch)($input, $output, $items);
        }

        return $exitCode;
    }

    /**
     * @return 0|positive-int
     */
    private function runTolerantSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        try {
            ($this->runSingleCommand)($item, $input, $output);

            return 0;
        } catch (Throwable $throwable) {
            return $this->errorHandler->handleError($item, $throwable, $logger);
        }
    }

    /**
     * @param int<1,max> $segmentSize
     * @param int<1,max> $numberOfProcesses
     */
    private function createProcessLauncher(
        int $segmentSize,
        int $numberOfProcesses,
        InputInterface $input,
        Logger $logger
    ): ProcessLauncher {
        return $this->processLauncherFactory->create(
            $this->childCommandFactory->createChildCommand($input),
            $this->workingDirectory,
            $this->extraEnvironmentVariables,
            $numberOfProcesses,
            $segmentSize,
            $logger,
            fn (string $type, string $buffer, int $index, int $pid) => $this->processChildOutput($buffer, $logger, $index, $pid),
            $this->processTick,
        );
    }

    /**
     * Called whenever data is received in the main process from a child process.
     *
     * @param string $buffer The received data
     */
    private function processChildOutput(
        string $buffer,
        Logger $logger,
        int $index,
        int $pid
    ): void {
        $progressSymbol = $this->progressSymbol;
        $charactersCount = mb_substr_count($buffer, $progressSymbol);

        // Display unexpected output
        if ($charactersCount !== mb_strlen($buffer)) {
            $logger->logUnexpectedOutput($buffer, $progressSymbol, $index, $pid);
        }

        $logger->advance($charactersCount);
    }

    private static function validateBatchSize(int $batchSize): void
    {
        Assert::greaterThan(
            $batchSize,
            0,
            sprintf(
                'Expected the batch size to be 1 or greater. Got "%s".',
                $batchSize,
            ),
        );
    }

    private static function validateSegmentSize(int $segmentSize): void
    {
        Assert::greaterThan(
            $segmentSize,
            0,
            sprintf(
                'Expected the segment size to be 1 or greater. Got "%s".',
                $segmentSize,
            ),
        );
    }

    private static function validateProgressSymbol(string $progressSymbol): void
    {
        $symbolLength = mb_strlen($progressSymbol);

        Assert::same(
            1,
            $symbolLength,
            sprintf(
                'Expected the progress symbol length to be 1. Got "%s" for "%s".',
                $symbolLength,
                $progressSymbol,
            ),
        );
    }
}
