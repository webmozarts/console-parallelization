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

use Webmozarts\Console\Parallelization\Logger\Logger;

final class SymfonyProcessLauncherFactory implements ProcessLauncherFactory
{
    public function __construct(private readonly SymfonyProcessFactory $processFactory)
    {
    }

    /**
     * @param list<string>                                             $command
     * @param array<string, string>|null                               $extraEnvironmentVariables
     * @param positive-int                                             $numberOfProcesses
     * @param positive-int                                             $segmentSize
     * @param callable(positive-int|0, int|null, string, string): void $processOutput             A PHP callback which is run whenever
     *                                                                                            there is some output available on
     *                                                                                            STDOUT or STDERR.
     * @param callable(): void                                         $tick
     */
    public function create(
        string $phpExecutable,
        array $command,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        callable $processOutput,
        callable $tick
    ): ProcessLauncher {
        return new SymfonyProcessLauncher(
            $phpExecutable,
            $command,
            $workingDirectory,
            $extraEnvironmentVariables,
            $numberOfProcesses,
            $segmentSize,
            $logger,
            $processOutput,
            $tick,
            $this->processFactory,
        );
    }
}
