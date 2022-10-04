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

use function chr;
use const DIRECTORY_SEPARATOR;
use function getcwd;
use const STDIN;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use Webmozarts\Console\Parallelization\Process\SymfonyProcessLauncherFactory;

final class ParallelExecutorFactory
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

    private string $phpExecutable;

    private string $progressSymbol;

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
     * @param resource                                                     $childSourceStream
     * @param positive-int                                                 $batchSize
     * @param positive-int                                                 $segmentSize
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param array<string, string>                                        $extraEnvironmentVariables
     */
    private function __construct(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler,
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
        ProcessLauncherFactory $processLauncherFactory
    ) {
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
    }

    /**
     * @param callable(InputInterface):list<string>                  $fetchItems
     * @param callable(string, InputInterface, OutputInterface):void $runSingleCommand
     * @param callable(int):string                                   $getItemName
     */
    public static function create(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler
    ): self {
        return new self(
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
            STDIN,
            50,
            50,
            self::getNoopCallable(),
            self::getNoopCallable(),
            self::getNoopCallable(),
            self::getNoopCallable(),
            chr(254),
            PhpExecutableFinder::find(),
            self::getScriptPath(),
            getcwd(),
            null,
            new SymfonyProcessLauncherFactory(),
        );
    }

    /**
     * @param resource $childSourceStream
     */
    public function withChildSourceStream($childSourceStream): self
    {
        $clone = clone $this;
        $clone->childSourceStream = $childSourceStream;

        return $clone;
    }

    /**
     * The number of items to process in a batch. Multiple batches can be
     * executed within the main and child processes. This allows to early fetch
     * aggregates or persist aggregates in batches for performance optimizations
     * for example.
     *
     * @param positive-int $batchSize
     */
    public function withBatchSize(int $batchSize): self
    {
        $clone = clone $this;
        $clone->batchSize = $batchSize;

        return $clone;
    }

    /**
     * The number of items to process per child process. This is done in order
     * to circumvent some issues recurring to long living processes such as
     * memory leaks.
     *
     * This value is only relevant when ran with child process(es).
     *
     * @param positive-int $segmentSize
     */
    public function withSegmentSize(int $segmentSize): self
    {
        $clone = clone $this;
        $clone->segmentSize = $segmentSize;

        return $clone;
    }

    /**
     * Callable executed at the very beginning of the main process.
     *
     * @param callable(InputInterface, OutputInterface):void $runBeforeFirstCommand
     */
    public function withRunBeforeFirstCommand(callable $runBeforeFirstCommand): self
    {
        $clone = clone $this;
        $clone->runBeforeFirstCommand = $runBeforeFirstCommand;

        return $clone;
    }

    /**
     * Callable executed at the very end of the main process.
     *
     * @param callable(InputInterface, OutputInterface):void $runAfterLastCommand
     */
    public function withRunAfterLastCommand(callable $runAfterLastCommand): self
    {
        $clone = clone $this;
        $clone->runAfterLastCommand = $runAfterLastCommand;

        return $clone;
    }

    /**
     * Callable executed before executing all the items of the current batch. It
     * is executed in either the main or child process depending on whether
     * child processes are spawned.
     *
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     */
    public function withRunBeforeBatch(callable $runBeforeBatch): self
    {
        $clone = clone $this;
        $clone->runBeforeBatch = $runBeforeBatch;

        return $clone;
    }

    /**
     * Callable executed after executing all the items of the current batch. It
     * is executed in either the main or child process depending on whether
     * child processes are spawned.
     *
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     */
    public function withRunAfterBatch(callable $runAfterBatch): self
    {
        $clone = clone $this;
        $clone->runAfterBatch = $runAfterBatch;

        return $clone;
    }

    /**
     * The symbol for communicating progress from the child to the main process
     * when displaying the progress bar.
     */
    public function withProgressSymbol(string $progressSymbol): self
    {
        $clone = clone $this;
        $clone->progressSymbol = $progressSymbol;

        return $clone;
    }

    /**
     * The path of the PHP executable. It is the executable that will be used
     * to spawn the child process(es).
     */
    public function withPhpExecutable(string $phpExecutable): self
    {
        $clone = clone $this;
        $clone->phpExecutable = $phpExecutable;

        return $clone;
    }

    /**
     * The path of the executable for the application. For example the path to
     * the Symfony bin/console script. It is the script that will be used to
     * spawn the child process(es).
     */
    public function withScriptPath(string $scriptPath): self
    {
        $clone = clone $this;
        $clone->scriptPath = $scriptPath;

        return $clone;
    }

    /**
     * The working directory for the child process(es).
     */
    public function withWorkingDirectory(string $workingDirectory): self
    {
        $clone = clone $this;
        $clone->workingDirectory = $workingDirectory;

        return $clone;
    }

    /**
     * Configure the extra environment variables that are passed to the child
     * processes.
     *
     * @param array<string, string> $extraEnvironmentVariables
     */
    public function withExtraEnvironmentVariables(?array $extraEnvironmentVariables): self
    {
        $clone = clone $this;
        $clone->extraEnvironmentVariables = $extraEnvironmentVariables;

        return $clone;
    }

    public function withProcessLauncherFactory(ProcessLauncherFactory $processLauncherFactory): self
    {
        $clone = clone $this;
        $clone->processLauncherFactory = $processLauncherFactory;

        return $clone;
    }

    public function build(): ParallelExecutor
    {
        return new ParallelExecutor(
            $this->fetchItems,
            $this->runSingleCommand,
            $this->getItemName,
            $this->commandName,
            $this->commandDefinition,
            $this->errorHandler,
            $this->childSourceStream,
            $this->batchSize,
            $this->segmentSize,
            $this->runBeforeFirstCommand,
            $this->runAfterLastCommand,
            $this->runBeforeBatch,
            $this->runAfterBatch,
            $this->progressSymbol,
            $this->phpExecutable,
            $this->scriptPath,
            $this->workingDirectory,
            $this->extraEnvironmentVariables,
            $this->processLauncherFactory,
        );
    }

    private static function getNoopCallable(): callable
    {
        static $noop;

        // @codeCoverageIgnoreStart
        if (!isset($noop)) {
            $noop = static function () {};
        }
        // @codeCoverageIgnoreEnd

        return $noop;
    }

    private static function getScriptPath(): string
    {
        $pwd = $_SERVER['PWD'];
        $scriptName = $_SERVER['SCRIPT_NAME'];

        return 0 === mb_strpos($scriptName, $pwd)
            ? $scriptName
            : $pwd.DIRECTORY_SEPARATOR.$scriptName;
    }
}
