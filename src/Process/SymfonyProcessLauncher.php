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

namespace Webmozarts\Console\Parallelization\Process;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Logger\Logger;
use function count;
use function sprintf;
use const PHP_EOL;

/**
 * Launches a number of processes and distributes data among these processes.
 *
 * The distributed data set is passed to run(). The launcher spawns as many
 * processes as configured in the constructor. Each process receives a share
 * of the data set via its standard input, separated by newlines. The size
 * of this share can be configured in the constructor (the segment size).
 *
 * @phpstan-import-type ProcessOutput from ProcessLauncherFactory
 */
final class SymfonyProcessLauncher implements ProcessLauncher
{
    /**
     * @var list<string>
     */
    private array $command;

    private string $workingDirectory;

    /**
     * @var array<string, string>|null
     */
    private ?array $environmentVariables;

    /**
     * @var positive-int
     */
    private int $numberOfProcesses;

    /**
     * @var positive-int
     */
    private int $segmentSize;

    private Logger $logger;

    /**
     * @var ProcessOutput
     */
    private $processOutput;

    /**
     * @var array<positive-int|0, Process>
     */
    private array $runningProcesses = [];

    /**
     * @var callable
     */
    private $tick;

    private SymfonyProcessFactory $processFactory;

    /**
     * @param list<string>               $command
     * @param array<string, string>|null $extraEnvironmentVariables
     * @param positive-int               $numberOfProcesses
     * @param positive-int               $segmentSize
     * @param ProcessOutput              $processOutput             A PHP callback which is run whenever
     *                                                              there is some output available on
     *                                                              STDOUT or STDERR.
     * @param callable(): void           $tick
     */
    public function __construct(
        array $command,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        callable $processOutput,
        callable $tick,
        SymfonyProcessFactory $processFactory
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environmentVariables = $extraEnvironmentVariables;
        $this->numberOfProcesses = $numberOfProcesses;
        $this->segmentSize = $segmentSize;
        $this->logger = $logger;
        $this->processOutput = $processOutput;
        $this->tick = $tick;
        $this->processFactory = $processFactory;
    }

    public function run(iterable $items): int
    {
        /** @var InputStream|null $currentInputStream */
        $currentInputStream = null;
        $numberOfStreamedItems = 0;
        $exitCode = 0;

        foreach ($items as $item) {
            // Close the input stream if the segment is full
            if (null !== $currentInputStream && $numberOfStreamedItems >= $this->segmentSize) {
                $currentInputStream->close();
                $currentInputStream = null;

                $numberOfStreamedItems = 0;
            }

            // Wait until we can launch a new process
            while (null === $currentInputStream) {
                $exitCode += $this->freeTerminatedProcesses();

                $maxNumberOfRunningProcessesReached = count($this->runningProcesses) >= $this->numberOfProcesses;

                if (!$maxNumberOfRunningProcessesReached) {
                    $currentInputStream = new InputStream();
                    $numberOfStreamedItems = 0;

                    $this->startProcess($currentInputStream);

                    break;
                }

                ($this->tick)();
            }

            // Stream the data segment to the process' input stream
            $currentInputStream->write($item.PHP_EOL);

            ++$numberOfStreamedItems;
        }

        if (null !== $currentInputStream) {
            $currentInputStream->close();
        }

        // Waiting until all running processes are terminated
        while (count($this->runningProcesses) > 0) {
            $exitCode += $this->freeTerminatedProcesses();

            ($this->tick)();
        }

        return $exitCode;
    }

    private function startProcess(InputStream $inputStream): void
    {
        $index = count($this->runningProcesses);

        $process = $this->processFactory->startProcess(
            $index,
            $inputStream,
            $this->command,
            $this->workingDirectory,
            $this->environmentVariables,
            $this->processOutput,
        );

        $pid = $process->getPid();
        Assert::notNull(
            $pid,
            sprintf(
                'Expected the process #%d to have a PID. None found.',
                $index,
            ),
        );

        $this->logger->logChildProcessStarted(
            $index,
            $pid,
            $process->getCommandLine(),
        );

        $this->runningProcesses[] = $process;
    }

    /**
     * Searches for terminated processes and removes them from memory to make
     * space for new processes.
     *
     * @return 0|positive-int
     */
    private function freeTerminatedProcesses(): int
    {
        $exitCode = 0;

        foreach ($this->runningProcesses as $index => $process) {
            if (!$process->isRunning()) {
                $exitCode += $this->freeProcess($index, $process);
            }
        }

        return $exitCode;
    }

    /**
     * @param positive-int|0 $index
     */
    private function freeProcess(int $index, Process $process): int
    {
        $this->logger->logChildProcessFinished($index);

        unset($this->runningProcesses[$index]);

        return self::getExitCode($process);
    }

    /**
     * @return 0|positive-int
     */
    private static function getExitCode(Process $process): int
    {
        $exitCode = $process->getExitCode();

// @codeCoverageIgnoreStart
        if (null !== $exitCode && $exitCode < 0) {
            // A negative exit code indicates the process has been terminated by
            // a signal.
            // Technically it is incorrect to change the exit code sign here.
            // However, since we sum up the exit codes here we have no choice but
            // to do so as otherwise we could cancel out an exit code. For example
            // a child process that has -1 and the other one 1 the result would
            // be 0 for the main process exit code which would be incorrect.
            return -$exitCode;
        }
        // @codeCoverageIgnoreEnd

        Assert::notNull(
            $exitCode,
            'Expected the process to have an exit code. Got "null" instead.',
        );

        return $exitCode;
    }
}
