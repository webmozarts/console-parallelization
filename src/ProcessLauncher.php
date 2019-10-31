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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * Launches a number of processes and distributes data among these processes.
 *
 * The distributed data set is passed to run(). The launcher spawns as many
 * processes as configured in the constructor. Each process receives a share
 * of the data set via its standard input, separated by newlines. The size
 * of this share can be configured in the constructor (the segment size).
 */
class ProcessLauncher
{
    private $command;

    private $workingDirectory;

    private $environmentVariables;

    private $processLimit;

    private $segmentSize;

    private $logger;

    private $callback;

    /**
     * @var Process[]
     */
    private $runningProcesses = [];

    public function __construct(
        string $command,
        string $workingDirectory,
        array $environmentVariables,
        int $processLimit,
        int $segmentSize,
        ?LoggerInterface $logger,
        Closure $callback
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environmentVariables = $environmentVariables;
        $this->processLimit = $processLimit;
        $this->segmentSize = $segmentSize;
        $this->logger = $logger ?? new NullLogger();
        $this->callback = $callback;
    }

    /**
     * Runs child processes to process the given tasks.
     *
     * @param string[] $tasks The tasks to process. None of the tasks must
     *                        contain newlines
     */
    public function run(array $tasks): void
    {
        /** @var InputStream|null $currentInputStream */
        $currentInputStream = null;
        $numberOfStreamedTasks = 0;

        foreach ($tasks as $task) {
            // Close the input stream if the segment is full
            if (null !== $currentInputStream && $numberOfStreamedTasks >= $this->segmentSize) {
                $currentInputStream->close();

                $currentInputStream = null;
                $numberOfStreamedTasks = 0;
            }

            // Wait until we can launch a new process
            while (null === $currentInputStream) {
                $this->freeTerminatedProcesses();

                if (count($this->runningProcesses) < $this->processLimit) {
                    // Start a new process
                    $currentInputStream = new InputStream();
                    $numberOfStreamedTasks = 0;

                    $this->startProcess($currentInputStream);

                    break;
                }

                // 100ms
                usleep(100000);
            }

            // Stream the data segment to the process' input stream
            $currentInputStream->write($task."\n");

            ++$numberOfStreamedTasks;
        }

        if (null !== $currentInputStream) {
            $currentInputStream->close();
        }

        while (count($this->runningProcesses) > 0) {
            $this->freeTerminatedProcesses();

            // 100ms
            usleep(100000);
        }
    }

    /**
     * Starts a single process reading from the given input stream.
     */
    private function startProcess(InputStream $inputStream): void
    {
        $process = new Process(
            $this->command,
            $this->workingDirectory,
            $this->environmentVariables,
            null,
            null
        );

        $process->setInput($inputStream);
        $process->inheritEnvironmentVariables(true);
        $process->start($this->callback);

        $this->logger->debug('Command started: '.$this->command);

        $this->runningProcesses[] = $process;
    }

    /**
     * Searches for terminated processes and removes them from memory to make
     * space for new processes.
     */
    private function freeTerminatedProcesses(): void
    {
        foreach ($this->runningProcesses as $key => $process) {
            if (!$process->isRunning()) {
                $this->logger->debug('Command finished');

                unset($this->runningProcesses[$key]);
            }
        }
    }
}
