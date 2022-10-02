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
use function mb_strlen;
use function sprintf;
use const STDIN;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function trim;
use function usleep;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Process\ProcessLauncher;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use Webmozarts\Console\Parallelization\Process\StandardSymfonyProcessFactory;

final class ParallelExecutor
{
    /**
     * @var callable(InputInterface):list<string>
     */
    private $fetchItems;

    /**
     * @var callable(string, InputInterface, OutputInterface):void
     */
    private $runSingleCommand;

    /**
     * @var callable(int): string
     */
    private $getItemName;

    private string $commandName;

    private InputDefinition $commandDefinition;

    private ItemProcessingErrorHandler $errorHandler;

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
     * @param callable(InputInterface):list<string>                        $fetchItems
     * @param callable(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param callable(int):string                                         $getItemName
     * @param positive-int                                                 $batchSize
     * @param positive-int                                                 $segmentSize
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param array<string, string>                                        $extraEnvironmentVariables
     */
    public function __construct(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler,
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
        ProcessLauncherFactory $processLauncherFactory
    ) {
        self::validateBatchSize($batchSize);
        self::validateSegmentSize($segmentSize);
        self::validateScriptPath($scriptPath);
        self::validateProgressSymbol($progressSymbol);
        // TODO: validate that fetch items do not have new lines

        $this->fetchItems = $fetchItems;
        $this->runSingleCommand = $runSingleCommand;
        $this->getItemName = $getItemName;
        $this->commandName = $commandName;
        $this->commandDefinition = $commandDefinition;
        $this->errorHandler = $errorHandler;
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
    }

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
     */
    private function executeMainProcess(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger
    ): int {
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

        if (self::shouldSpawnChildProcesses(
            $numberOfItems,
            $segmentSize,
            $numberOfProcesses,
            $parallelizationInput->isNumberOfProcessesDefined(),
        )) {
            $this
                ->createProcessLauncher(
                    $segmentSize,
                    $numberOfProcesses,
                    $input,
                    $logger,
                )
                ->run($itemIterator->getItems());
        } else {
            $this->processItems(
                $itemIterator,
                $input,
                $output,
                $logger,
                static fn () => $logger->advance(),
            );
        }

        $logger->finish($itemName);

        ($this->runAfterLastCommand)($input, $output);

        // TODO: use the exit code constants once we drop support for Symfony 4.4
        return 0;
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
    ): int {
        $itemIterator = ChunkedItemsIterator::fromStream(
            STDIN,
            $this->batchSize,
        );

        $progressSymbol = $this->progressSymbol;

        $this->processItems(
            $itemIterator,
            $input,
            $output,
            $logger,
            static fn () => $output->write($progressSymbol),
        );

        // TODO: use the exit code constants once we drop support for Symfony 4.4
        return 0;
    }

    /**
     * @param callable():void $advance
     */
    private function processItems(
        ChunkedItemsIterator $itemIterator,
        InputInterface $input,
        OutputInterface $output,
        Logger $logger,
        callable $advance
    ): void {
        foreach ($itemIterator->getItemChunks() as $items) {
            ($this->runBeforeBatch)($input, $output, $items);

            foreach ($items as $item) {
                $this->runTolerantSingleCommand($item, $input, $output, $logger);

                $advance();
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
     * @param 0|positive-int $numberOfItems
     * @param positive-int   $segmentSize
     * @param positive-int   $numberOfProcesses
     */
    private static function shouldSpawnChildProcesses(
        int $numberOfItems,
        int $segmentSize,
        int $numberOfProcesses,
        bool $umberOfProcessesDefined
    ): bool {
        return $numberOfItems > $segmentSize
            && ($numberOfProcesses > 1 || $umberOfProcessesDefined);
    }

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
            static fn () => usleep(1000),   // 1ms
            new StandardSymfonyProcessFactory(),
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
                array_slice(
                    array_map('strval', $input->getArguments()),
                    1,
                ),
            ),
            '--child',
        ]);
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
