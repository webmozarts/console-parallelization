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

use DomainException;
use Throwable;
use Webmozarts\Console\Parallelization\Configuration;

final class FakeLogger implements Logger
{
    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        throw new DomainException('Unexpected call.');
    }

    public function logStart(?int $numberOfItems): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logAdvance(int $steps = 1): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logFinish(string $itemName): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logChildProcessFinished(int $index): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logUnexpectedChildProcessOutput(int $index, ?int $pid, string $buffer, string $progressSymbol): void
    {
        throw new DomainException('Unexpected call.');
    }
}
