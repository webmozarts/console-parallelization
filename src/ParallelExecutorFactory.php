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
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Input\ChildCommandFactory;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use Webmozarts\Console\Parallelization\Process\ProcessLauncherFactory;
use Webmozarts\Console\Parallelization\Process\StandardSymfonyProcessFactory;
use Webmozarts\Console\Parallelization\Process\SymfonyProcessLauncherFactory;
use function chr;
use function explode;
use function is_string;
use function Safe\getcwd;
use function str_starts_with;
use const DIRECTORY_SEPARATOR;
use const STDIN;

final class ParallelExecutorFactory
{
    private const int CHILD_POLLING_IN_MICRO_SECONDS = 1000;    // 1ms

    private bool $useDefaultBatchSize = true;

    /**
     * @param Closure(InputInterface):iterable<string>                    $fetchItems
     * @param Closure(string, InputInterface, OutputInterface):void       $runSingleCommand
     * @param Closure(positive-int|0|null):string                         $getItemName
     * @param resource                                                    $childSourceStream
     * @param positive-int                                                $batchSize
     * @param positive-int                                                $segmentSize
     * @param Closure(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     * @param Closure(InputInterface, OutputInterface):void               $runAfterLastCommand
     * @param Closure(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     * @param Closure(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     * @param list<string>                                                $phpExecutable
     * @param array<string, string>                                       $extraEnvironmentVariables
     * @param Closure(): void                                             $processTick
     */
    private function __construct(
        private Closure $fetchItems,
        private Closure $runSingleCommand,
        private Closure $getItemName,
        private string $commandName,
        private InputDefinition $commandDefinition,
        private ErrorHandler $errorHandler,
        private $childSourceStream,
        private int $batchSize,
        private int $segmentSize,
        private Closure $runBeforeFirstCommand,
        private Closure $runAfterLastCommand,
        private Closure $runBeforeBatch,
        private Closure $runAfterBatch,
        private string $progressSymbol,
        private array $phpExecutable,
        private string $scriptPath,
        private string $workingDirectory,
        private ?array $extraEnvironmentVariables,
        private ProcessLauncherFactory $processLauncherFactory,
        private Closure $processTick
    ) {
    }

    /**
     * @param Closure(InputInterface):iterable<string>              $fetchItems
     * @param Closure(string, InputInterface, OutputInterface):void $runSingleCommand
     * @param Closure(positive-int|0|null):string                   $getItemName
     */
    public static function create(
        Closure $fetchItems,
        Closure $runSingleCommand,
        Closure $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ErrorHandler $errorHandler
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
            self::getNoopClosure(),
            self::getNoopClosure(),
            self::getNoopClosure(),
            self::getNoopClosure(),
            chr(254),
            PhpExecutableFinder::find(),
            self::getScriptPath(),
            getcwd(),
            null,
            new SymfonyProcessLauncherFactory(
                new StandardSymfonyProcessFactory(),
            ),
            static fn () => usleep(self::CHILD_POLLING_IN_MICRO_SECONDS),
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
        $clone->useDefaultBatchSize = false;

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
     * Closure executed at the very beginning of the main process.
     *
     * @param Closure(InputInterface, OutputInterface):void $runBeforeFirstCommand
     */
    public function withRunBeforeFirstCommand(Closure $runBeforeFirstCommand): self
    {
        $clone = clone $this;
        $clone->runBeforeFirstCommand = $runBeforeFirstCommand;

        return $clone;
    }

    /**
     * Closure executed at the very end of the main process.
     *
     * @param Closure(InputInterface, OutputInterface):void $runAfterLastCommand
     */
    public function withRunAfterLastCommand(Closure $runAfterLastCommand): self
    {
        $clone = clone $this;
        $clone->runAfterLastCommand = $runAfterLastCommand;

        return $clone;
    }

    /**
     * Closure executed before executing all the items of the current batch. It
     * is executed in either the main or child process depending on whether
     * child processes are spawned.
     *
     * @param Closure(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     */
    public function withRunBeforeBatch(Closure $runBeforeBatch): self
    {
        $clone = clone $this;
        $clone->runBeforeBatch = $runBeforeBatch;

        return $clone;
    }

    /**
     * Closure executed after executing all the items of the current batch. It
     * is executed in either the main or child process depending on whether
     * child processes are spawned.
     *
     * @param Closure(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     */
    public function withRunAfterBatch(Closure $runAfterBatch): self
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
     *
     * @param string|list<string> $phpExecutable
     */
    public function withPhpExecutable(string|array $phpExecutable): self
    {
        $normalizedExecutable = is_string($phpExecutable)
            ? explode(' ', $phpExecutable)
            : $phpExecutable;

        $clone = clone $this;
        $clone->phpExecutable = $normalizedExecutable;

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

    /**
     * @param Closure(): void $processTick
     */
    public function withProcessTick(Closure $processTick): self
    {
        $clone = clone $this;
        $clone->processTick = $processTick;

        return $clone;
    }

    public function build(): ParallelExecutor
    {
        return new ParallelExecutor(
            $this->fetchItems,
            $this->runSingleCommand,
            $this->getItemName,
            $this->errorHandler,
            $this->childSourceStream,
            $this->useDefaultBatchSize ? $this->segmentSize : $this->batchSize,
            $this->segmentSize,
            $this->runBeforeFirstCommand,
            $this->runAfterLastCommand,
            $this->runBeforeBatch,
            $this->runAfterBatch,
            $this->progressSymbol,
            new ChildCommandFactory(
                $this->phpExecutable,
                $this->scriptPath,
                $this->commandName,
                $this->commandDefinition,
            ),
            $this->workingDirectory,
            $this->extraEnvironmentVariables,
            $this->processLauncherFactory,
            $this->processTick,
        );
    }

    private static function getNoopClosure(): Closure
    {
        static $noop;

        // @codeCoverageIgnoreStart
        if (!isset($noop)) {
            $noop = static function (): void {};
        }
        // @codeCoverageIgnoreEnd

        return $noop;
    }

    private static function getScriptPath(): string
    {
        $pwd = $_SERVER['PWD'] ?? getcwd();
        $scriptName = $_SERVER['SCRIPT_NAME'];

        return (str_starts_with($scriptName, $pwd)
                || str_starts_with($scriptName, DIRECTORY_SEPARATOR)
        )
            ? $scriptName
            : $pwd.DIRECTORY_SEPARATOR.$scriptName;
    }
}
