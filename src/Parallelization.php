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

use Symfony\Component\Console\Input\InputDefinition;
use function func_num_args;
use const DIRECTORY_SEPARATOR;
use function getcwd;
use function sprintf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandlerLogger;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetContainerErrorHandler;
use Webmozarts\Console\Parallelization\Logger\DebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;

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

        return $this
            ->getParallelExecutableFactory(
                $this->getValidatedBatchSize(),
                $this->getValidatedSegmentSize(),
                fn (InputInterface $input) => $this->fetchItems($input),
                fn (string $item, InputInterface $input, OutputInterface $output) => $this->runSingleCommand($item, $input, $output),
                fn (int $count) => $this->getItemName($count),
                $this->getName(),
                $this->getDefinition(),
                $this->createItemErrorHandler(),
            )
            ->build()
            ->execute(
                $parallelizationInput,
                $input,
                $output,
                $this->createLogger($output),
            );
    }

    protected function createLogger(OutputInterface $output): Logger
    {
        return new StandardLogger(
            $output,
            (new Terminal())->getWidth(),
            new DebugProgressBarFactory(),
            new ConsoleLogger($output),
        );
    }

    protected function createItemErrorHandler(): ItemProcessingErrorHandler
    {
        $errorHandler = new ResetContainerErrorHandler($this->getContainer());

        return $this->logError
            ? new ItemProcessingErrorHandlerLogger($errorHandler)
            : $errorHandler;
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
    protected function getParallelExecutableFactory(
        int $batchSize,
        int $segmentSize,
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler
    ): ParallelExecutorFactory
    {
        return ParallelExecutorFactory::create(
            $batchSize,
            $segmentSize,
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
        );
    }
}
