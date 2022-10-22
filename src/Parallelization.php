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

use Closure;
use Symfony\Bundle\FrameworkBundle\Console\Application as FrameworkBundleApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
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
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use function chr;
use function dirname;
use function getcwd;
use function getenv;
use function realpath;

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
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override the method ::createErrorHandler() instead.
     */
    private bool $logError = true;

    /**
     * Provided by Symfony Command class.
     *
     * @return string The command name
     */
    abstract public function getName();

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Use ParallelizationInput::configureCommand() instead.
     */
    protected static function configureParallelization(Command $command): void
    {
        ParallelizationInput::configureCommand($command);
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
     * @return iterable<string> The items to process
     */
    abstract protected function fetchItems(InputInterface $input): iterable;

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
        // The only exception is if you need the whole BC layer with the API
        // from 1.x.
        $factory = ParallelExecutorFactory::create(
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
        );

        $container = $this->getContainer();

        $progressSymbol = $this->getProgressSymbol();
        $legacyDefaultProgressSymbol = chr(254);
        if ($legacyDefaultProgressSymbol !== $progressSymbol) {
            Deprecation::trigger(
                'The method ::getProgressSymbol() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your progress symbol to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withProgressSymbol($progressSymbol);
        }

        $phpExecutable = $this->detectPhpExecutable();
        $legacyDefaultPhpExecutable = PhpExecutableFinder::tryToFind();
        if ($phpExecutable !== $legacyDefaultPhpExecutable) {
            Deprecation::trigger(
                'The method ::detectPhpExecutable() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your PHP executable path to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withPhpExecutable($phpExecutable);
        }

        $workingDirectory = $this->getWorkingDirectory($container);
        $legacyDefaultWorkingDirectory = dirname($container->getParameter('kernel.project_dir'));
        if ($workingDirectory !== $legacyDefaultWorkingDirectory) {
            Deprecation::trigger(
                'The method ::getWorkingDirectory() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your working directory path to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withWorkingDirectory($workingDirectory);
        }

        $environmentVariables = $this->getEnvironmentVariables($container);
        $legacyDefaultEnvironmentVariables = [
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME'),
            'SYMFONY_DEBUG' => $container->getParameter('kernel.debug'),
            'SYMFONY_ENV' => $container->getParameter('kernel.environment'),
        ];
        if ($environmentVariables !== $legacyDefaultEnvironmentVariables) {
            Deprecation::trigger(
                'The method ::getEnvironmentVariables() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your extra environment variables to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withExtraEnvironmentVariables($environmentVariables);
        }

        $segmentSize = $this->getSegmentSize();
        $legacyDefaultSegmentSize = 50;
        if ($segmentSize !== $legacyDefaultSegmentSize) {
            Deprecation::trigger(
                'The method ::getSegmentSize() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your segment size to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withSegmentSize($segmentSize);
        }

        $batchSize = $this->getBatchSize();
        $legacyDefaultBatchSize = $segmentSize;
        if ($batchSize !== $legacyDefaultBatchSize) {
            Deprecation::trigger(
                'The method ::getBatchSize() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your batch size to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withBatchSize($batchSize);
        }

        $consolePath = $this->getConsolePath();
        $legacyDefaultConsolePath = realpath(getcwd().'/bin/console');
        if ($consolePath !== $legacyDefaultConsolePath) {
            Deprecation::trigger(
                'The method ::getConsolePath() is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to register your script path to the factory.',
                __FUNCTION__,
            );

            $factory = $factory->withScriptPath($consolePath);
        }

        return $factory
            ->withRunBeforeFirstCommand(Closure::fromCallable([$this, 'runBeforeFirstCommand']))
            ->withRunAfterLastCommand(Closure::fromCallable([$this, 'runAfterLastCommand']))
            ->withRunBeforeBatch(Closure::fromCallable([$this, 'runBeforeBatch']))
            ->withRunAfterBatch(Closure::fromCallable([$this, 'runAfterBatch']));
    }

    // TODO: probably worth passing the output here in case
    protected function createErrorHandler(): ErrorHandler
    {
        $errorHandler = new ThrowableCodeErrorHandler(
            ResetServiceErrorHandler::forContainer($this->getContainer()),
        );

        if (!$this->logError) {
            Deprecation::trigger(
                'The %s#logError property is deprecated and will be removed in 3.0.0. Override the ::%s() method instead to produce the desired error handler.',
                self::class,
                __FUNCTION__,
            );

            return $errorHandler;
        }

        return new LoggingErrorHandler($errorHandler);
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

    protected function getContainer(): ?ContainerInterface
    {
        // The container is required to reset the container upon failure to
        // avoid things such as a broken UoW or entity manager.
        //
        // If no such behaviour is desired, ::createItemErrorHandler() can be
        // overridden to provide a different error handler.
        $application = $this->getApplication();

        if ($application instanceof FrameworkBundleApplication) {
            return $application->getKernel()->getContainer();
        }

        return null;
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register the progress symbol to the factory
     *             instead.
     */
    private static function getProgressSymbol(): string
    {
        return chr(254);
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register the PHP executable path to the
     *             factory instead.
     */
    private static function detectPhpExecutable(): string
    {
        return PhpExecutableFinder::find();
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register the working directory path to the
     *             factory instead.
     */
    private static function getWorkingDirectory(ContainerInterface $container): string
    {
        return dirname($container->getParameter('kernel.project_dir'));
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register the working directory path to the
     *             factory instead.
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
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your own callable. Note that having
     *             a method with the same name is still fine, but it needs to be registered to the
     *             factory and not extend the original one.
     */
    protected function runBeforeFirstCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your own callable. Note that having
     *             a method with the same name is still fine, but it needs to be registered to the
     *             factory and not extend the original one.
     */
    protected function runAfterLastCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your own callable. Note that having
     *             a method with the same name is still fine, but it needs to be registered to the
     *             factory and not extend the original one.
     *
     * @param list<string> $items
     */
    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your own callable. Note that having
     *             a method with the same name is still fine, but it needs to be registered to the
     *             factory and not extend the original one.
     *
     * @param list<string> $items
     */
    protected function runAfterBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your segment size
     *             to the factory instead.
     */
    protected function getSegmentSize(): int
    {
        return 50;
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your batch size
     *             to the factory instead.
     */
    protected function getBatchSize(): int
    {
        return $this->getSegmentSize();
    }

    /**
     * @deprecated Deprecated since 2.0.0 and will be removed in 3.0.0. Override
     *             ::getParallelExecutableFactory() to register your console path
     *             to the factory instead.
     */
    protected function getConsolePath(): string
    {
        return realpath(getcwd().'/bin/console');
    }
}
