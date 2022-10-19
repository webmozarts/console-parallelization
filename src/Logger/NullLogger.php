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

    public function startProgress(?int $numberOfItems): void
    {
        // Do nothing.
    }

    public function advance(int $steps = 1): void
    {
        // Do nothing.
    }

    public function finish(string $itemName): void
    {
        // Do nothing.
    }

    public function logUnexpectedOutput(string $buffer, string $progressSymbol): void
    {
        // Do nothing.
    }

    public function logCommandStarted(string $commandName): void
    {
        // Do nothing.
    }

    public function logCommandFinished(): void
    {
        // Do nothing.
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        // Do nothing.
    }
}
