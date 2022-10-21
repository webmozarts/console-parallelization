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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Input\InputOptionsSerializer;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Process\ProcessLauncher;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function implode;
use function mb_strlen;
use function sprintf;

final class ParallelExecutor
{
    /**
     * @var callable(InputInterface):iterable<string>
     */
    private $fetchItems;

    /**
     * @var callable(string, InputInterface, OutputInterface):int<0,255>
     */
    private $runSingleCommand;

    /**
     * @var callable(positive-int|0|null): string
     */
    private $getItemName;

    private string $commandName;

    private InputDefinition $commandDefinition;

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

    private string $phpExecutable;

    private string $scriptPath;

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
        string $commandName,
        InputDefinition $commandDefinition,
        ErrorHandler $errorHandler,
        $childSourceStream,
        int $batchSize,
        int $segmentSize,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        string $progressSymbol,
        string $phpExecutable,
        string $scriptPath,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        ProcessLauncherFactory $processLauncherFactory,
        callable $processTick
    ) {
        self::validateSegmentSize($segmentSize);
        self::validateBatchSize($batchSize);
        self::validateScriptPath($scriptPath);
        self::validateProgressSymbol($progressSymbol);

        $this->fetchItems = $fetchItems;
        $this->runSingleCommand = $runSingleCommand;
        $this->getItemName = $getItemName;
        $this->commandName = $commandName;
        $this->commandDefinition = $commandDefinition;
        $this->errorHandler = $errorHandler;
        $this->childSourceStream = $childSourceStream;
        $this->batchSize = $batchSize;
        $this->segmentSize = $segmentSize;
        $this->runBeforeFirstCommand = $runBeforeFirstCommand;
        $this->runAfterLastCommand = $runAfterLastCommand;
        $this->runBeforeBatch = $runBeforeBatch;
        $this->runAfterBatch = $runAfterBatch;
        $this->progressSymbol = $progressSymbol;
        $this->phpExecutable = $phpExecutable;
        $this->scriptPath = $scriptPath;
        $this->workingDirectory = $workingDirectory;
        $this->extraEnvironmentVariables = $extraEnvironmentVariables;
        $this->processLauncherFactory = $processLauncherFactory;
        $this->processTick = $processTick;
    }

    /**
     * @return int<0,255>
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
     * @return int<0,255>
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
     * @return int<0,255>
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
     * @return int<0,255>
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
     * @return int<0,255>
     */
    private function runTolerantSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        try {
            return ($this->runSingleCommand)($item, $input, $output);
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
        $enrichedChildCommand = array_merge(
            $this->createChildCommand($input),
            // Forward all the options except for "processes" to the children
            // this way the children can inherit the options such as env
            // or no-debug.
            InputOptionsSerializer::serialize(
                $this->commandDefinition,
                $input,
                ['child', 'processes'],
            ),
        );

        return $this->processLauncherFactory->create(
            $enrichedChildCommand,
            $this->workingDirectory,
            $this->extraEnvironmentVariables,
            $numberOfProcesses,
            $segmentSize,
            $logger,
            fn (string $type, string $buffer) => $this->processChildOutput($buffer, $logger),
            $this->processTick,
        );
    }

    /**
     * @return list<string>
     */
    private function createChildCommand(InputInterface $input): array
    {
        return array_filter([
            $this->phpExecutable,
            $this->scriptPath,
            $this->commandName,
            implode(
                ' ',
                // TODO: this looks suspicious: why do we need to take the first arg?
                //      why is this not a specific arg?
                //      why do we include optional arguments? (cf. options)
                //      maybe has to do with the item arg but in that case it is incorrect...
                array_filter(
                    array_slice(
                        array_map('strval', $input->getArguments()),
                        1,
                    ),
                ),
            ),
            '--child',
        ]);
    }

    /**
     * Called whenever data is received in the main process from a child process.
     *
     * @param string $buffer The received data
     */
    private function processChildOutput(
        string $buffer,
        Logger $logger
    ): void {
        $progressSymbol = $this->progressSymbol;
        $charactersCount = mb_substr_count($buffer, $progressSymbol);

        // Display unexpected output
        if ($charactersCount !== mb_strlen($buffer)) {
            $logger->logUnexpectedOutput($buffer, $progressSymbol);
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

    private static function validateScriptPath(string $scriptPath): void
    {
        Assert::fileExists(
            $scriptPath,
            sprintf(
                'The script file could not be found at the path "%s" (working directory: %s)',
                $scriptPath,
                getcwd(),
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
