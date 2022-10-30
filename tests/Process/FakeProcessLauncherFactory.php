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
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class FakeProcessLauncherFactory implements ProcessLauncherFactory
{
    public function create(
        array $command,
        string $workingDirectory,
        ?array $extraEnvironmentVariables,
        int $numberOfProcesses,
        int $segmentSize,
        Logger $logger,
        callable $processOutput,
        callable $tick
    ): ProcessLauncher {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
