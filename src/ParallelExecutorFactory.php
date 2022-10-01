<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use function chr;
use function getcwd;
use const DIRECTORY_SEPARATOR;

final class ParallelExecutorFactory
{
    /**
     * @var positive-int
     */
    private int $batchSize;

    /**
     * @var positive-int
     */
    private int $segmentSize;

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
    public static function create(
        int $batchSize,
        int $segmentSize,
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler
    ): self
    {
        return new self(
            $batchSize,
            $segmentSize,
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
            self::getNoopCallable(),
            self::getNoopCallable(),
            self::getNoopCallable(),
            self::getNoopCallable(),
            self::getProgressSymbol(),
            self::getScriptPath(),
            self::getPhpExecutable(),
            self::getWorkingDirectory(),
            null,
        );
    }

    private static function getProgressSymbol(): string
    {
        static $progressSymbol;

        if (!isset($progressSymbol)) {
            $progressSymbol = chr(254);
        }

        return $progressSymbol;
    }

    private static function getNoopCallable(): callable
    {
        static $noop;

        if (!isset($noop)) {
            $noop = static function () {};
        }

        return $noop;
    }

    private static function getScriptPath(): callable
    {
        static $scriptPath;

        if (!isset($scriptPath)) {
            $pwd = $_SERVER['PWD'];
            $scriptName = $_SERVER['SCRIPT_NAME'];

            $scriptPath = 0 === mb_strpos($scriptName, $pwd)
                ? $scriptName
                : $pwd.DIRECTORY_SEPARATOR.$scriptName;
        }

        return $scriptPath;
    }

    private static function getPhpExecutable(): callable
    {
        static $phpExecutable;

        if (!isset($phpExecutable)) {
            $phpExecutable = PhpExecutableFinder::find();
        }

        return $phpExecutable;
    }

    private static function getWorkingDirectory(): callable
    {
        static $cwd;

        if (!isset($cwd)) {
            $cwd = getcwd();
        }

        return $cwd;
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
    private function __construct(
        int $batchSize,
        int $segmentSize,
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler,
        callable $runBeforeFirstCommand,
        callable $runAfterLastCommand,
        callable $runBeforeBatch,
        callable $runAfterBatch,
        string $progressSymbol,
        string $scriptPath,
        string $phpExecutable,
        string $workingDirectory,
        ?array $extraEnvironmentVariables
    ) {
        $this->batchSize = $batchSize;
        $this->segmentSize = $segmentSize;
        $this->fetchItems = $fetchItems;
        $this->runSingleCommand = $runSingleCommand;
        $this->getItemName = $getItemName;
        $this->commandName = $commandName;
        $this->commandDefinition = $commandDefinition;
        $this->errorHandler = $errorHandler;
        $this->runBeforeFirstCommand = $runBeforeFirstCommand;
        $this->runAfterLastCommand = $runAfterLastCommand;
        $this->runBeforeBatch = $runBeforeBatch;
        $this->runAfterBatch = $runAfterBatch;
        $this->progressSymbol = $progressSymbol;
        $this->scriptPath = $scriptPath;
        $this->phpExecutable = $phpExecutable;
        $this->workingDirectory = $workingDirectory;
        $this->extraEnvironmentVariables = $extraEnvironmentVariables;
    }

    /**
     * @param callable(InputInterface, OutputInterface):void               $runBeforeFirstCommand
     */
    public function withRunBeforeFirstCommand(callable $runBeforeFirstCommand): self
    {
        $clone = clone $this;
        $clone->runBeforeFirstCommand = $runBeforeFirstCommand;

        return $clone;
    }

    /**
     * @param callable(InputInterface, OutputInterface):void               $runAfterLastCommand
     */
    public function withRunAfterLastCommand(callable $runAfterLastCommand): self
    {
        $clone = clone $this;
        $clone->runAfterLastCommand = $runAfterLastCommand;

        return $clone;
    }

    /**
     * @param callable(InputInterface, OutputInterface, list<string>):void $runBeforeBatch
     */
    public function withRunBeforeBatch(callable $runBeforeBatch): self
    {
        $clone = clone $this;
        $clone->runBeforeBatch = $runBeforeBatch;

        return $clone;
    }

    /**
     * @param callable(InputInterface, OutputInterface, list<string>):void $runAfterBatch
     */
    public function withRunAfterBatch(callable $runAfterBatch): self
    {
        $clone = clone $this;
        $clone->runAfterBatch = $runAfterBatch;

        return $clone;
    }

    public function withProgressSymbol(string $progressSymbol): self
    {
        $clone = clone $this;
        $clone->runAfterBatch = $progressSymbol;

        return $clone;
    }

    public function withScriptPath(string $scriptPath): self
    {
        $clone = clone $this;
        $clone->scriptPath = $scriptPath;

        return $clone;
    }

    public function withPhpExecutable(string $phpExecutable): self
    {
        $clone = clone $this;
        $clone->phpExecutable = $phpExecutable;

        return $clone;
    }

    public function withWorkingDirectory(string $workingDirectory): self
    {
        $clone = clone $this;
        $clone->workingDirectory = $workingDirectory;

        return $clone;
    }

    /**
     * @param array<string, string>                                        $extraEnvironmentVariables
     */
    public function withExtraEnvironmentVariables(?array $extraEnvironmentVariables): self
    {
        $clone = clone $this;
        $clone->extraEnvironmentVariables = $extraEnvironmentVariables;

        return $clone;
    }

    public function build(): ParallelExecutor
    {
        // TODO:
        //  - script path validation
        //  - symbol validation
        //  - php executable validation
        //  - working dir validation
        return new ParallelExecutor(
            $this->batchSize,
            $this->segmentSize,
            $this->fetchItems,
            $this->runSingleCommand,
            $this->getItemName,
            $this->commandName,
            $this->commandDefinition,
            $this->errorHandler,
            $this->runBeforeFirstCommand,
            $this->runAfterLastCommand,
            $this->runBeforeBatch,
            $this->runAfterBatch,
            $this->progressSymbol,
            $this->scriptPath,
            $this->phpExecutable,
            $this->workingDirectory,
            $this->extraEnvironmentVariables,
        );
    }
}
