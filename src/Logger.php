<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

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

    public function advance(): void;

    public function end(): void;

    public function logUnexpectedOutput(string $buffer): void;
}
