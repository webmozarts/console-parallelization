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

    public function startProgress(?int $numberOfItems): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function advance(int $steps = 1): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function finish(string $itemName): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logUnexpectedOutput(string $buffer, string $progressSymbol): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logCommandStarted(string $commandName): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logCommandFinished(): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        throw new DomainException('Unexpected call.');
    }
}
