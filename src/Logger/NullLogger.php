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

final class NullLogger implements Logger
{
    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        // Do nothing.
    }

    public function logStart(?int $numberOfItems): void
    {
        // Do nothing.
    }

    public function logAdvance(int $steps = 1): void
    {
        // Do nothing.
    }

    public function logFinish(string $itemName): void
    {
        // Do nothing.
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        // Do nothing.
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        // Do nothing.
    }

    public function logChildProcessFinished(int $index): void
    {
        // Do nothing.
    }

    public function logUnexpectedChildProcessOutput(int $index, ?int $pid, string $buffer, string $progressSymbol): void
    {
        // Do nothing.
    }
}
