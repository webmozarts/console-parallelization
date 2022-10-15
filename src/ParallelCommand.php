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

abstract class ParallelCommand extends Command
{
    // TODO: simply add the Parallelization trait for 3.x where all the BC
    //  layer of the trait is removed.

    protected function configure(): void
    {
        ParallelizationInput::configureParallelization($this);
    }

    /**
     * Fetches the items that should be processed.
     *
     * Typically, you will fetch all the items of the database objects that
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the main process.
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
    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $parallelizationInput = ParallelizationInput::fromInput($input);

        return $this
            ->getParallelExecutableFactory(
                fn (InputInterface $input) => $this->fetchItems($input),
                fn (string $item, InputInterface $input, OutputInterface $output) => $this->runSingleCommand($item, $input, $output),
                fn (int $count) => $this->getItemName($count),
                $this->getName(),
                $this->getDefinition(),
                $this->createErrorHandler(),
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

    protected function createErrorHandler(): ErrorHandler
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