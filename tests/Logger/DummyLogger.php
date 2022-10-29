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
use function func_get_args;

final class DummyLogger implements Logger
{
    public array $records = [];

    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logStart(?int $numberOfItems): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logAdvance(int $steps = 1): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logFinish(string $itemName): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logChildProcessFinished(int $index): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $type,
        string $buffer,
        string $progressSymbol
    ): void {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }
}
