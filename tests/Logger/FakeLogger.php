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
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class FakeLogger implements Logger
{
    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logStart(?int $numberOfItems): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logAdvance(int $steps = 1): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logFinish(string $itemName): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logChildProcessFinished(int $index): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $type,
        string $buffer,
        string $progressSymbol
    ): void {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
