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

/**
 * Launches a number of processes and distributes data among these processes.
 *
 * The distributed data set is passed to run(). The launcher spawns as many
 * processes as configured in the constructor. Each process receives a share
 * of the data set via its standard input, separated by newlines. The size
 * of this share can be configured in the constructor (the segment size).
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
     * @var callable(string, string): void
     */
    private $callback;

    /**
     * @var Process[]
     */
    private array $runningProcesses = [];

    /**
     * @var callable
     */
    private $tick;

    private SymfonyProcessFactory $processFactory;

    /**
     * @param list<string>                   $command
     * @param array<string, string>|null     $extraEnvironmentVariables
     * @param positive-int                   $numberOfProcesses
     * @param positive-int                   $segmentSize
     * @param callable(string, string): void $callback
     * @param callable(): void               $tick
     */
    public function __construct(
        array $command,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        callable $callback,
        callable $tick,
        SymfonyProcessFactory $processFactory
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environmentVariables = $extraEnvironmentVariables;
        $this->numberOfProcesses = $numberOfProcesses;
        $this->segmentSize = $segmentSize;
        $this->logger = $logger;
        $this->callback = $callback;
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
        $process = $this->processFactory->startProcess(
            $inputStream,
            $this->command,
            $this->workingDirectory,
            $this->environmentVariables,
            $this->callback,
        );

        $this->logger->logChildProcessStarted($process->getCommandLine());

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
     * @return 0|positive-int
     */
    private function freeProcess(int $index, Process $process): int
    {
        $this->logger->logChildProcessFinished();

        unset($this->runningProcesses[$index]);

        $exitCode = $process->getExitCode();
        Assert::natural($exitCode, 'Expected the process to be finished and return a valid exit code.');

        return $exitCode;
    }
}
