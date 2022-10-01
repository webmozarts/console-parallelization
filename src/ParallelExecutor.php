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

use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function implode;
use const STDIN;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function trim;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Logger\Logger;

final class ParallelExecutor
{
    private string $progressSymbol;

    /**
     * @var positive-int
     */
    private int $batchSize;

    /**
     * @var positive-int
     */
    private int $segmentSize;

    /**
     * @var callable(InputInterface):list<string>
     */
    private $fetchItems;

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

    /**
     * @var callable(string, InputInterface, OutputInterface):void
     */
    private $runSingleCommand;

    private string $scriptPath;
    private string $phpExecutable;
    private string $commandName;
    private string $workingDirectory;

    /**
     * @var array<string, string>|null
     */
    private ?array $extraEnvironmentVariables;

    private InputDefinition $commandDefinition;

    /**
     * @var callable(int): string
     */
    private $getItemName;

    private ItemProcessingErrorHandler $errorHandler;

    /**
     * @param positive-int                                                 $batchSize
     * @param positive-int                                                 $segmentSize
     * @param callable(InputInterface):list<string>                        $fetchItems
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param callable(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param callable(int):string                                         $getItemName
     * @param array<string, string>                                        $extraEnvironmentVariables
     */
    public function __construct(
        string $progressSymbol,
        int $batchSize,
        int $segmentSize,
        callable $fetchItems,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        callable $runSingleCommand,
        callable $getItemName,
        string $scriptPath,
        string $phpExecutable,
        string $commandName,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler
    ) {
        $this->progressSymbol = $progressSymbol;
        $this->batchSize = $batchSize;
        $this->fetchItems = $fetchItems;
        $this->runBeforeFirstCommand = $runBeforeFirstCommand;
        $this->runAfterLastCommand = $runAfterLastCommand;
        $this->runBeforeBatch = $runBeforeBatch;
        $this->runAfterBatch = $runAfterBatch;
        $this->runSingleCommand = $runSingleCommand;
        $this->segmentSize = $segmentSize;
        $this->scriptPath = $scriptPath;
        $this->phpExecutable = $phpExecutable;
        $this->commandName = $commandName;
        $this->workingDirectory = $workingDirectory;
        $this->extraEnvironmentVariables = $extraEnvironmentVariables;
        $this->commandDefinition = $commandDefinition;
        $this->getItemName = $getItemName;
        $this->errorHandler = $errorHandler;
    }

    public function execute(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
        if ($parallelizationInput->isChildProcess()) {
            $this->executeChildProcess($input, $output, $logger);

            return 0;
        }

        $this->executeMasterProcess(
            $parallelizationInput,
            $input,
            $output,
            $logger,
        );

        return 0;
    }

    /**
     * Executes the master process.
     *
     * The master process spawns as many child processes as set in the
     * "--processes" option. Each of the child processes receives a segment of
     * items of the processed data set and terminates. As long as there is data
     * left to process, new child processes are spawned automatically.
     */
    private function executeMasterProcess(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): void {
        ($this->runBeforeFirstCommand)($input, $output);

        $isNumberOfProcessesDefined = $parallelizationInput->isNumberOfProcessesDefined();
        $numberOfProcesses = $parallelizationInput->getNumberOfProcesses();

        $batchSize = $this->batchSize;
        $segmentSize = $this->segmentSize;

        $itemIterator = ChunkedItemsIterator::fromItemOrCallable(
            $parallelizationInput->getItem(),
            fn () => ($this->fetchItems)($input),
            $batchSize,
        );

        $numberOfItems = $itemIterator->getNumberOfItems();

        $config = new Configuration(
            $isNumberOfProcessesDefined,
            $numberOfProcesses,
            $numberOfItems,
            $segmentSize,
            $batchSize,
        );

        $numberOfSegments = $config->getNumberOfSegments();
        $numberOfBatches = $config->getNumberOfBatches();
        $itemName = ($this->getItemName)($numberOfItems);

        $logger->logConfiguration(
            $segmentSize,
            $batchSize,
            $numberOfItems,
            $numberOfSegments,
            $numberOfBatches,
            $numberOfProcesses,
            $itemName,
        );

        $logger->startProgress($numberOfItems);

        if ($numberOfItems <= $segmentSize
            || (1 === $numberOfProcesses && !$parallelizationInput->isNumberOfProcessesDefined())
        ) {
            // Run in the master process

            foreach ($itemIterator->getItemChunks() as $items) {
                ($this->runBeforeBatch)($input, $output, $items);

                foreach ($items as $item) {
                    $this->runTolerantSingleCommand($item, $input, $output, $logger);

                    $logger->advance();
                }

                ($this->runAfterBatch)($input, $output, $items);
            }
        } else {
            // Distribute if we have multiple segments
            $commandTemplate = array_merge(
                array_filter([
                    $this->phpExecutable,
                    $this->scriptPath,
                    $this->commandName,
                    implode(
                        ' ',
                        array_slice(
                            array_map('strval', $input->getArguments()),
                            1,
                        ),
                    ),
                    '--child',
                ]),
                // Forward all the options except for "processes" to the children
                // this way the children can inherit the options such as env
                // or no-debug.
                InputOptionsSerializer::serialize(
                    $this->commandDefinition,
                    $input,
                    ['child', 'processes'],
                ),
            );

            $processLauncher = new ProcessLauncher(
                $commandTemplate,
                $this->workingDirectory,
                $this->extraEnvironmentVariables,
                $numberOfProcesses,
                $segmentSize,
                $logger,
                fn (string $type, string $buffer) => $this->processChildOutput($buffer, $logger),
            );

            $processLauncher->run($itemIterator->getItems());
        }

        $logger->finish($itemName);

        ($this->runAfterLastCommand)($input, $output);
    }

    /**
     * Executes the child process.
     *
     * This method reads the items from the standard input that the master process
     * piped into the process. These items are passed to runSingleCommand() one
     * by one.
     */
    private function executeChildProcess(
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): void {
        $advancementChar = $this->progressSymbol;

        $itemIterator = ChunkedItemsIterator::fromStream(
            STDIN,
            $this->batchSize,
        );

        foreach ($itemIterator->getItemChunks() as $items) {
            ($this->runBeforeBatch)($input, $output, $items);

            foreach ($items as $item) {
                $this->runTolerantSingleCommand($item, $input, $output, $logger);

                $output->write($advancementChar);
            }

            ($this->runAfterBatch)($input, $output, $items);
        }
    }

    private function runTolerantSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): void {
        try {
            ($this->runSingleCommand)(trim($item), $input, $output);
        } catch (Throwable $throwable) {
            $this->errorHandler->handleError($item, $throwable, $logger);
        }
    }

    /**
     * Called whenever data is received in the master process from a child process.
     *
     * @param string $buffer The received data
     */
    private function processChildOutput(
        string $buffer,
        Logger $logger
    ): void {
        $progressSymbol = $this->progressSymbol;
        $chars = mb_substr_count($buffer, $progressSymbol);

        // Display unexpected output
        if ($chars !== mb_strlen($buffer)) {
            $logger->logUnexpectedOutput($buffer, $progressSymbol);
        }

        $logger->advance($chars);
    }
}
