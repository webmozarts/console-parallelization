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

interface Logger
{
    public function logConfiguration(
        int $segmentSize,
        int $batchSize,
        int $numberOfItems,
        int $numberOfSegments,
        int $numberOfBatches,
        int $numberOfProcesses,
        string $itemName
    ): void;

    public function startProgress(int $numberOfItems): void;

    public function advance(int $steps = 1): void;

    public function finish(string $itemName): void;

    public function logUnexpectedOutput(string $buffer): void;

    public function logCommandStarted(string $string): void;

    public function logCommandFinished(string $string): void;
}
