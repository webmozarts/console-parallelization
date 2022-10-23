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

interface Logger
{
    /**
     * @param positive-int        $batchSize
     * @param 0|positive-int|null $numberOfItems
     */
    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void;

    /**
     * @param 0|positive-int|null $numberOfItems
     */
    public function startProgress(?int $numberOfItems): void;

    public function advance(int $steps = 1): void;

    public function finish(string $itemName): void;

    public function logUnexpectedOutput(string $buffer, string $progressSymbol, int $index, int $pid): void;

    public function logCommandStarted(int $index, string $commandName, int $pid): void;

    public function logCommandFinished(int $index): void;

    public function logItemProcessingFailed(string $item, Throwable $throwable): void;
}
