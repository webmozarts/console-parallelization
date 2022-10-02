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

use function func_get_args;
use Throwable;

final class DummyLogger implements Logger
{
    public array $records = [];

    public function logConfiguration(
        int $segmentSize,
        int $batchSize,
        int $numberOfItems,
        int $numberOfSegments,
        int $numberOfBatches,
        int $numberOfProcesses,
        string $itemName
    ): void {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function startProgress(int $numberOfItems): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function advance(int $steps = 1): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function finish(string $itemName): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logUnexpectedOutput(string $buffer, string $progressSymbol): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logCommandStarted(string $commandName): void
    {
        $this->records[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function logCommandFinished(): void
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
}
