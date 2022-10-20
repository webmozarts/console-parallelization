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

interface ProcessLauncherFactory
{
    /**
     * @param list<string>                   $command
     * @param array<string, string>|null     $extraEnvironmentVariables
     * @param positive-int                   $numberOfProcesses
     * @param positive-int                   $segmentSize
     * @param callable(string, string): void $callback
     * @param callable(): void               $tick
     */
    public function create(
        array $command,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        callable $callback,
        callable $tick
    ): ProcessLauncher;
}
