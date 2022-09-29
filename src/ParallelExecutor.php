<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use resource;
use RuntimeException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use Webmozart\Assert\Assert;
use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_map;
use function array_merge;
use function array_slice;
use function chr;
use function class_exists;
use function dirname;
use function getcwd;
use function getenv;
use function implode;
use function preg_match;
use function realpath;
use function sprintf;
use function str_pad;
use function str_replace;
use function trim;
use const STDIN;
use const STR_PAD_BOTH;

final class ParallelExecutor
{
    /**
     * @var resource
     */
    private $sourceStream;

    private Logger $logger;

    /**
     * @var non-empty-string
     */
    private string $progressSymbol;

    /**
     * @var callable(InputInterface, OutputInterface):void
     */
    private $runBeforeFirstCommand;

    /**
     * @var callable(InputInterface, OutputInterface):void
     */
    private $runAfterLastCommand;

    private int $batchSize;
    private int $segmentSize;
    /**
     * @var callable
     */
    private $fetchItems;

    private string $singularItemName;
    private string $pluralItemName;

    /**
     * @var callable(InputInterface, OutputInterface, list<string>):void
     */
    private $runBeforeBatch;

    /**
     * @var callable(InputInterface, OutputInterface, list<string>):void
     */
    private $runAfterBatch;

    /**
     * @param resource $sourceStream
     * @param Logger $logger
     * @param non-empty-string $progressSymbol
     * @param callable(InputInterface):list<string> $fetchItems
     * @param callable(InputInterface, OutputInterface):void $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param positive-int $batchSize
     * @param positive-int $segmentSize
     */
    public function __construct(
        $sourceStream,
        Logger $logger,
        string $progressSymbol,
        callable $fetchItems,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        int $batchSize,
        int $segmentSize,
        string $singularItemName,
        string $pluralItemName
    ) {
        $this->sourceStream = $sourceStream;
        $this->logger = $logger;
        $this->progressSymbol = $progressSymbol;
        $this->fetchItems = $fetchItems;
        $this->runBeforeFirstCommand = $runBeforeFirstCommand;
        $this->runAfterLastCommand = $runAfterLastCommand;
        $this->batchSize = $batchSize;
        $this->segmentSize = $segmentSize;
        $this->singularItemName = $singularItemName;
        $this->pluralItemName = $pluralItemName;
        $this->runBeforeBatch = $runBeforeBatch;
        $this->runAfterBatch = $runAfterBatch;
    }

    /**
     * Executes the parallelized command.
     */
    public function execute(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output
    ): int
    {
        if ($parallelizationInput->isChildProcess()) {
            $this->executeChildProcess($input, $output);

            return 0;
        }

        $this->executeMasterProcess($parallelizationInput, $input, $output);

        return 0;
    }

    private function executeMasterProcess(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output
    ): void {
        ($this->runBeforeFirstCommand)($input, $output);

        $isNumberOfProcessesDefined = $parallelizationInput->isNumberOfProcessesDefined();
        $numberOfProcesses = $parallelizationInput->getNumberOfProcesses();

        $itemIterator = ChunkedItemsIterator::fromItemOrCallable(
            $parallelizationInput->getItem(),
            fn () => ($this->fetchItems)($input),
            $this->batchSize,
        );

        $numberOfItems = $itemIterator->getNumberOfItems();

        $config = new Configuration(
            $isNumberOfProcessesDefined,
            $numberOfProcesses,
            $numberOfItems,
            $this->segmentSize,
            $this->batchSize,
        );

        $numberOfSegments = $config->getNumberOfSegments();
        $numberOfBatches = $config->getNumberOfBatches();

        $this->logger->logConfiguration(
            $this->segmentSize,
            $this->batchSize,
            $numberOfItems,
            $numberOfSegments,
            $numberOfBatches,
            $numberOfProcesses,
            $this->getItemName($numberOfItems),
        );

        $this->logger->startProgress($numberOfItems);

        $shouldLaunchChildProcesses = self::shouldLaunchChildProcesses(
            $numberOfItems,
            $this->segmentSize,
            $numberOfProcesses,
            $parallelizationInput->isNumberOfProcessesDefined(),
        );

        if ($shouldLaunchChildProcesses) {
            $this->launchChildProcesses();
        } else {
            $this->executeWithinMasterProcess(
                $itemIterator,
                $input,
                $output,
            );
        }

        $this->logger->end();

        ($this->runAfterLastCommand)($input, $output);
    }

    private function executeChildProcess(
        InputInterface $input,
        OutputInterface $output
    ): void {
        $advance = $this->createChildProcessStepper(
            $this->progressSymbol,
            $output,
        );

        $itemIterator = ChunkedItemsIterator::fromStream(
            $this->sourceStream,
            $this->batchSize,
        );

        $this->processItems(
            $itemIterator,
            $advance,
            $input,
            $output,
        );
    }

    private function processItems(
        ChunkedItemsIterator $itemIterator,
        callable $advance,
        InputInterface $input,
        OutputInterface $output
    ): void
    {
        foreach ($itemIterator->getItemChunks() as $items) {
            ($this->runBeforeBatch)($input, $output, $items);

            foreach ($items as $item) {
                ($this->runTolerantSingleCommand)($item, $input, $output);

                $advance();
            }

            ($this->runAfterBatch)($input, $output, $items);
        }
    }

    private function createChildProcessStepper(
        string $progressSymbol,
        OutputInterface $output
    ): callable
    {
        return static fn () => $output->write($progressSymbol);
    }

    /**
     * @param 0|positive-int $numberOfItems
     * @param positive-int $segmentSize
     * @param positive-int $numberOfProcesses
     */
    private static function shouldLaunchChildProcesses(
        int $numberOfItems,
        int $segmentSize,
        int $numberOfProcesses,
        bool $isNumberOfProcessesDefined
    ): bool
    {
        return $numberOfItems > $segmentSize
            && ($numberOfProcesses > 1
                || $isNumberOfProcessesDefined
            );
    }

    private function executeWithinMasterProcess(
        ChunkedItemsIterator $itemIterator,
        InputInterface $input,
        OutputInterface $output
    ): void
    {
        $this->processItems(
            $itemIterator,
            fn () => $this->logger->advance(),
            $input,
            $output,
        );
    }

    private function launchChildProcesses(): void
    {
        // Distribute if we have multiple segments
        $consolePath = $this->getConsolePath();
        Assert::fileExists(
            $consolePath,
            sprintf('The bin/console file could not be found at %s.', getcwd()),
        );

        $commandTemplate = array_merge(
            array_filter([
                self::detectPhpExecutable(),
                $consolePath,
                $this->getName(),
                implode(
                    ' ',
                    array_slice(
                        array_map('strval', $input->getArguments()),
                        1,
                    ),
                ),
                '--child',
            ]),
            $this->serializeInputOptions($input, ['child', 'processes']),
        );

        $terminalWidth = (new Terminal())->getWidth();

        // @TODO: can be removed once ProcessLauncher accepts command arrays
        $tempProcess = new Process($commandTemplate);
        $commandString = $tempProcess->getCommandLine();

        $processLauncher = new ProcessLauncher(
            $commandString,
            self::getWorkingDirectory($this->getContainer()),
            $this->getEnvironmentVariables($this->getContainer()),
            $numberOfProcesses,
            $this->segmentSize,
            // TODO: offer a way to create the process launcher in a different manner
            new ConsoleLogger($output),
            function (string $type, string $buffer) use ($progressBar, $output, $terminalWidth) {
                $this->processChildOutput($buffer, $progressBar, $output, $terminalWidth);
            },
        );

        $processLauncher->run($itemIterator->getItems());
    }

    /**
     * @param 0|positive-int $numberOfItems
     */
    private function getItemName(int $numberOfItems): string
    {
        return 0 === $numberOfItems
            ? $this->singularItemName
            : $this->pluralItemName;
    }
}
