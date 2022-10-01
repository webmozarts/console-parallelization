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
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Webmozarts\Console\Parallelization\Logger\Logger;

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
    /**
     * @var list<string>
     */
    private array $command;

    private string $workingDirectory;

    /**
     * @var array<string, string>
     */
    private array $environmentVariables;

    /**
     * @var positive-int
     */
    private int $numberOfProcesses;

    /**
     * @var positive-int
     */
    private int $segmentSize;

    private Logger $logger;

    private Closure $callback;

    /**
     * @var Process[]
     */
    private array $runningProcesses = [];

    /**
     * @param list<string>          $command
     * @param array<string, string> $environmentVariables
     * @param positive-int          $numberOfProcesses
     * @param positive-int          $segmentSize
     */
    public function __construct(
        array $command,
        string $workingDirectory,
        array $environmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        Closure $callback
    ) {
        $this->command = $command;
        $this->workingDirectory = $workingDirectory;
        $this->environmentVariables = $environmentVariables;
        $this->numberOfProcesses = $numberOfProcesses;
        $this->segmentSize = $segmentSize;
        $this->logger = $logger;
        $this->callback = $callback;
    }

    /**
     * Runs child processes to process the given items.
     *
     * @param string[] $items The items to process. None of the items must
     *                        contain newlines
     */
    public function run(array $items): void
    {
        /** @var InputStream|null $currentInputStream */
        $currentInputStream = null;
        $numberOfStreamedItems = 0;

        foreach ($items as $item) {
            // Close the input stream if the segment is full
            if (null !== $currentInputStream && $numberOfStreamedItems >= $this->segmentSize) {
                $currentInputStream->close();

                $currentInputStream = null;
                $numberOfStreamedItems = 0;
            }

            // Wait until we can launch a new process
            while (null === $currentInputStream) {
                $this->freeTerminatedProcesses();

                if (count($this->runningProcesses) < $this->numberOfProcesses) {
                    // Start a new process
                    $currentInputStream = new InputStream();
                    $numberOfStreamedItems = 0;

                    $this->startProcess($currentInputStream);

                    break;
                }

                // 1ms
                usleep(1000);
            }

            // Stream the data segment to the process' input stream
            $currentInputStream->write($item.PHP_EOL);

            ++$numberOfStreamedItems;
        }

        if (null !== $currentInputStream) {
            $currentInputStream->close();
        }

        while (count($this->runningProcesses) > 0) {
            $this->freeTerminatedProcesses();

            // 1ms
            usleep(1000);
        }
    }

    /**
     * Starts a single process reading from the given input stream.
     */
    private function startProcess(InputStream $inputStream): void
    {
        $process = new Process(...[
            $this->command,
            $this->workingDirectory,
            $this->environmentVariables,
            null,
            null,
        ]);

        $process->setInput($inputStream);
        // TODO: remove the following once dropping Symfony 4.4. Environment
        //  variables are always inherited as of 5.0
        if (method_exists($process, 'inheritEnvironmentVariables')) {
            $process->inheritEnvironmentVariables(true);
        }
        $process->start($this->callback);

        $this->logger->logCommandStarted($process->getCommandLine());

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
                $this->logger->logCommandFinished();

                unset($this->runningProcesses[$key]);
            }
        }
    }
}
