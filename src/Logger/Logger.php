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

namespace Webmozarts\Console\Parallelization\Logger;

use Throwable;
use Webmozarts\Console\Parallelization\Configuration;

interface Logger
{
    /**
     * Logs the configuration before the start of the processing.
     *
     * @param positive-int        $batchSize
     * @param 0|positive-int|null $numberOfItems
     * @param string              $itemName      Name of the item; Already in the singular or plural
     *                                           form.
     */
    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void;

    /**
     * @param 0|positive-int|null $numberOfItems
     */
    public function logStart(?int $numberOfItems): void;

    /**
     * @param positive-int|0 $steps
     */
    public function logAdvance(int $steps = 1): void;

    /**
     * @param string $itemName Name of the item; Already in the singular or plural form.
     */
    public function logFinish(string $itemName): void;

    public function logItemProcessingFailed(string $item, Throwable $throwable): void;

    /**
     * @param positive-int|0 $index       Index of the process amoung the list of running processes.
     * @param string         $commandName Executed command for the child process.To not confuse
     *                                    with the Symfony command name which is just an element of
     *                                    the command.
     */
    public function logChildProcessStarted(int $index, int $pid, string $commandName): void;

    /**
     * @param positive-int|0 $index Index of the process amoung the list of running processes.
     */
    public function logChildProcessFinished(int $index): void;

    /**
     * Logs the "unexpected" child output. By unexpected is meant that the main
     * process expects the child to output the progress symbol to communicate its
     * progression. Any other sort of output is considered "unexpected".
     *
     * @param string         $buffer Child process output.
     * @param positive-int|0 $index  Index of the process amoung the list of running processes.
     * @param int|null       $pid    The child process PID. It can be null if the process is no
     *                               longer running.
     */
    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $buffer,
        string $progressSymbol
    ): void;
}
