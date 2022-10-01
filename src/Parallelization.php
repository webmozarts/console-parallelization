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
use function getcwd;
use function implode;
use function realpath;
use RuntimeException;
use function sprintf;
use const STDIN;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use function trim;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Logger\DebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;

/**
 * Adds parallelization capabilities to console commands.
 *
 * Make sure to call configureParallelization() in your configure() method!
 *
 * You must implement the following methods in your command:
 *
 *  * fetchItems(): Returns all the items that you want to process as
 *    strings. Typically, you will return IDs of database objects here.
 *  * runSingleCommand(): Executes the command for a single item.
 *  * getItemName(): Returns a human readable name of the processed items.
 *
 * You can improve the performance of your command by making use of batching.
 * Batching allows you to process multiple items together, for example to
 * persist them in a batch to reduce the number of I/O operations.
 *
 * To enable batching, you will typically implement runAfterBatch() and persist
 * the changes done in multiple calls of runSingleCommand().
 *
 * The batch size is determined by getBatchSize() and defaults to the segment
 * size. The segment size is the number of items a worker (child) process
 * consumes before it dies. This means that, by default, a child process will
 * process all its items, persist them in a batch and then die. If you want
 * to improve the performance of your command, try to tweak getSegmentSize()
 * first. Optionally, you can tweak getBatchSize() to process multiple batches
 * in each child process.
 */
trait Parallelization
{
    private bool $logError = true;

    /**
     * Provided by Symfony Command class.
     *
     * @return string The command name
     */
    abstract public function getName();

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    protected static function configureParallelization(Command $command): void
    {
        ParallelizationInput::configureParallelization($command);
    }

    /**
     * Provided by Symfony Command class.
     *
     * @return ContainerInterface
     */
    abstract protected function getContainer();

    /**
     * Provided by Symfony Command class.
     *
     * @return Application
     */
    abstract protected function getApplication();

    /**
     * Fetches the items that should be processed.
     *
     * Typically, you will fetch all the items of the database objects that
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the master process.
     *
     * @param InputInterface $input The console input
     *
     * @return string[] The items to process
     */
    abstract protected function fetchItems(InputInterface $input): array;

    /**
     * Processes an item in the child process.
     */
    abstract protected function runSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void;

    /**
     * Returns the name of each item in lowercase letters.
     *
     * For example, this method could return "contact" if the count is one and
     * "contacts" otherwise.
     *
     * @param int $count The number of items
     *
     * @return string The name of the item in the correct plurality
     */
    abstract protected function getItemName(int $count): string;

    /**
     * Returns the environment variables that are passed to the child processes.
     *
     * @param ContainerInterface $container The service containers
     *
     * @return string[] A list of environment variable names and values
     */
    protected function getEnvironmentVariables(ContainerInterface $container): array
    {
        return [
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME'),
            'SYMFONY_DEBUG' => $container->getParameter('kernel.debug'),
            'SYMFONY_ENV' => $container->getParameter('kernel.environment'),
        ];
    }

    /**
     * Method executed at the very beginning of the master process.
     */
    protected function runBeforeFirstCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * Method executed at the very end of the master process.
     */
    protected function runAfterLastCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * Method executed before executing all the items of the current batch.
     * This method is executed in both the master and child process.
     *
     * @param string[] $items
     */
    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * Method executed after executing all the items of the current batch.
     * This method is executed in both the master and child process.
     *
     * @param string[] $items
     */
    protected function runAfterBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * Returns the number of items to process per child process. This is
     * done in order to circumvent some issues recurring to long living
     * processes such as memory leaks.
     *
     * This value is only relevant when ran with child process(es).
     */
    protected function getSegmentSize(): int
    {
        return 50;
    }

    /**
     * Returns the number of items to process in a batch. Multiple batches
     * can be executed within the master and child processes. This allows to
     * early fetch aggregates or persist aggregates in batches for performance
     * optimizations.
     */
    protected function getBatchSize(): int
    {
        return $this->getSegmentSize();
    }

    /**
     * Executes the parallelized command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parallelizationInput = ParallelizationInput::fromInput($input);

        if ($parallelizationInput->isChildProcess()) {
            $this->executeChildProcess($input, $output);

            return 0;
        }

        $this->executeMasterProcess($parallelizationInput, $input, $output);

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
    protected function executeMasterProcess(
        ParallelizationInput $parallelizationInput,
        InputInterface $input,
        OutputInterface $output
    ): void {
        $this->runBeforeFirstCommand($input, $output);

        $isNumberOfProcessesDefined = $parallelizationInput->isNumberOfProcessesDefined();
        $numberOfProcesses = $parallelizationInput->getNumberOfProcesses();

        $batchSize = $this->getValidatedBatchSize();
        $segmentSize = $this->getValidatedSegmentSize();

        $itemIterator = ChunkedItemsIterator::fromItemOrCallable(
            $parallelizationInput->getItem(),
            fn () => $this->fetchItems($input),
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
        $itemName = $this->getItemName($numberOfItems);

        $logger = $this->createLogger($output);

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
                $this->runBeforeBatch($input, $output, $items);

                foreach ($items as $item) {
                    $this->runTolerantSingleCommand($item, $input, $output);

                    $logger->advance();
                }

                $this->runAfterBatch($input, $output, $items);
            }
        } else {
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
                // Forward all the options except for "processes" to the children
                // this way the children can inherit the options such as env
                // or no-debug.
                InputOptionsSerializer::serialize(
                    $this->getDefinition(),
                    $input,
                    ['child', 'processes'],
                ),
            );

            $processLauncher = new ProcessLauncher(
                $commandTemplate,
                self::getWorkingDirectory($this->getContainer()),
                $this->getEnvironmentVariables($this->getContainer()),
                $numberOfProcesses,
                $segmentSize,
                $logger,
                fn (string $type, string $buffer) => $this->processChildOutput($buffer, $logger),
            );

            $processLauncher->run($itemIterator->getItems());
        }

        $logger->finish($itemName);

        $this->runAfterLastCommand($input, $output);
    }

    /**
     * Get the path of the executable Symfony bin console.
     */
    protected function getConsolePath(): string
    {
        return realpath(getcwd().'/bin/console');
    }

    /**
     * Executes the child process.
     *
     * This method reads the items from the standard input that the master process
     * piped into the process. These items are passed to runSingleCommand() one
     * by one.
     */
    protected function executeChildProcess(
        InputInterface $input,
        OutputInterface $output
    ): void {
        $advancementChar = self::getProgressSymbol();

        $itemIterator = ChunkedItemsIterator::fromStream(
            STDIN,
            $this->getValidatedBatchSize(),
        );

        foreach ($itemIterator->getItemChunks() as $items) {
            $this->runBeforeBatch($input, $output, $items);

            foreach ($items as $item) {
                $this->runTolerantSingleCommand($item, $input, $output);

                $output->write($advancementChar);
            }

            $this->runAfterBatch($input, $output, $items);
        }
    }

    protected function createLogger(OutputInterface $output): Logger
    {
        return new StandardLogger(
            $output,
            self::getProgressSymbol(),
            (new Terminal())->getWidth(),
            new DebugProgressBarFactory(),
            new ConsoleLogger($output),
        );
    }

    /**
     * @internal
     * @return positive-int
     */
    private function getValidatedSegmentSize(): int
    {
        $segmentSize = $this->getSegmentSize();

        Assert::greaterThan(
            $segmentSize,
            0,
            sprintf(
                'Expected the segment size to be 1 or greater. Got "%s".',
                $segmentSize,
            ),
        );

        return $segmentSize;
    }

    /**
     * @internal
     * @return positive-int
     */
    private function getValidatedBatchSize(): int
    {
        $batchSize = $this->getBatchSize();

        Assert::greaterThan(
            $batchSize,
            0,
            sprintf(
                'Expected the batch size to be 1 or greater. Got "%s".',
                $batchSize,
            ),
        );

        return $batchSize;
    }

    /**
     * Returns the symbol for communicating progress from the child to the
     * master process when displaying the progress bar.
     */
    private static function getProgressSymbol(): string
    {
        return chr(254);
    }

    /**
     * Detects the path of the PHP interpreter.
     */
    private static function detectPhpExecutable(): string
    {
        $php = (new PhpExecutableFinder())->find();

        if (false === $php) {
            throw new RuntimeException('Cannot find php executable');
        }

        return $php;
    }

    /**
     * Returns the working directory for the child process.
     *
     * @param ContainerInterface $container The service container
     *
     * @return string The absolute path to the working directory
     */
    private static function getWorkingDirectory(ContainerInterface $container): string
    {
        return dirname($container->getParameter('kernel.project_dir'));
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
        $advancementChar = self::getProgressSymbol();
        $chars = mb_substr_count($buffer, $advancementChar);

        // Display unexpected output
        if ($chars !== mb_strlen($buffer)) {
            $logger->logUnexpectedOutput($buffer);
        }

        $logger->advance($chars);
    }

    private function runTolerantSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void {
        try {
            $this->runSingleCommand(trim($item), $input, $output);
        } catch (Throwable $exception) {
            if ($this->logError) {
                $output->writeln(sprintf(
                    "Failed to process \"%s\": %s\n%s",
                    trim($item),
                    $exception->getMessage(),
                    $exception->getTraceAsString(),
                ));
            }

            $container = $this->getContainer();

            if (
                (class_exists(ResetInterface::class) && $container instanceof ResetInterface)
                // TODO: to remove once we drop Symfony 4.4 support.
                || (class_exists(ResettableContainerInterface::class) && $container instanceof ResettableContainerInterface)
            ) {
                $container->reset();
            }
        }
    }
}
