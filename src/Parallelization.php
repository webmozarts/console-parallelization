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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\LoggingErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetContainerErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
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
     * Executes the parallelized command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parallelizationInput = ParallelizationInput::fromInput($input);

        return $this
            ->getParallelExecutableFactory(
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

    /**
     * @param callable(InputInterface):list<string>                  $fetchItems
     * @param callable(string, InputInterface, OutputInterface):void $runSingleCommand
     * @param callable(int):string                                   $getItemName
     */
    protected function getParallelExecutableFactory(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        return ParallelExecutorFactory::create(
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
        );
    }

    protected function createItemErrorHandler(): ErrorHandler
    {
        return new LoggingErrorHandler(
            new ResetContainerErrorHandler($this->getContainer()),
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

    protected function getContainer(): ContainerInterface
    {
        // The container is required to reset the container upon failure to
        // avoid things such as a broken UoW or entity manager.
        //
        // If no such behaviour is desired, ::createItemErrorHandler() can be
        // overridden to provide a different error handler.
        // @phpstan-ignore-next-line
        return $this->getApplication()->getKernel()->getContainer();
    }
}
