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

use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkBundleApplication;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\LoggingErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetServiceErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ThrowableCodeErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Logger\DebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;

/**
 * Adds parallelization capabilities to console commands.
 *
 * Make sure to call configureCommand() in your configure() method!
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
     * Fetches the items that should be processed.
     *
     * Typically, you will fetch all the items of the database objects that
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the main process.
     *
     * @return iterable<string> The items to process
     */
    abstract protected function fetchItems(InputInterface $input, OutputInterface $output): iterable;

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
     * @param positive-int|0|null $count The number of items (null if unknown)
     *
     * @return string The name of the item in the correct plurality
     */
    abstract protected function getItemName(?int $count): string;

    /**
     * Executes the parallelized command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parallelizationInput = ParallelizationInput::fromInput($input);

        $parallelExecutorFactory = $this->getParallelExecutableFactory(
            fn (InputInterface $input) => $this->fetchItems($input, $output),
            $this->runSingleCommand(...),
            $this->getItemName(...),
            $this->getName(),
            $this->getDefinition(),
            $this->createErrorHandler($input, $output),
        );

        return $this->configureParallelExecutableFactory(
            $parallelExecutorFactory,
            $input,
            $output,
        )
            ->build()
            ->execute(
                $parallelizationInput,
                $input,
                $output,
                $this->createLogger($input, $output),
            );
    }

    /**
     * @param callable(InputInterface):iterable<string>              $fetchItems
     * @param callable(string, InputInterface, OutputInterface):void $runSingleCommand
     * @param callable(positive-int|0|null):string                   $getItemName
     */
    protected function getParallelExecutableFactory(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        // If you are looking at this code to wonder if you should call it when
        // overriding this method, it is highly recommended you don't and just
        // call `ParallelExecutorFactory::create(...func_get_args())`.
        //
        // Configuring the factory is recommended to be done in
        // ::configureParallelExecutableFactory() instead which is
        // simpler to override, unless you _really_ need one of the
        // parameters passed to this method.
        return ParallelExecutorFactory::create(...func_get_args())
            ->withRunBeforeFirstCommand($this->runBeforeFirstCommand(...))
            ->withRunAfterLastCommand($this->runAfterLastCommand(...))
            ->withRunBeforeBatch($this->runBeforeBatch(...))
            ->withRunAfterBatch($this->runAfterBatch(...));
    }

    /**
     * This is an alternative to overriding ::getParallelExecutableFactory() which
     * is a bit less convenient due to the number of parameters.
     */
    protected function configureParallelExecutableFactory(
        ParallelExecutorFactory $parallelExecutorFactory,
        InputInterface $input,
        OutputInterface $output
    ): ParallelExecutorFactory {
        // This method will safely NEVER contain anything. It is only a
        // placeholder for the user to override so omitting
        // parent::configureParallelExecutableFactory() is perfectly safe.

        return $parallelExecutorFactory;
    }

    protected function createErrorHandler(InputInterface $input, OutputInterface $output): ErrorHandler
    {
        return new LoggingErrorHandler(
            new ThrowableCodeErrorHandler(
                ResetServiceErrorHandler::forContainer($this->getContainer()),
            ),
        );
    }

    protected function createLogger(InputInterface $input, OutputInterface $output): Logger
    {
        return new StandardLogger(
            $input,
            $output,
            (new Terminal())->getWidth(),
            new DebugProgressBarFactory(),
        );
    }

    protected function getContainer(): ?ContainerInterface
    {
        // The container is required to reset the container upon failure to
        // avoid things such as a broken UoW or entity manager.
        //
        // If no such behaviour is desired, ::createErrorHandler() can be
        // overridden to provide a different error handler.
        $application = $this->getApplication();

        if ($application instanceof FrameworkBundleApplication) {
            return $application->getKernel()->getContainer();
        }

        return null;
    }

    protected function runBeforeFirstCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    protected function runAfterLastCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * @param list<string> $items
     */
    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * @param list<string> $items
     */
    protected function runAfterBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }
}
