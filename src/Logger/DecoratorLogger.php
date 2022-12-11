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

/**
 * Base logger that can be extended to override only a few methods of a logger
 * and delegate the others to the decorated logger.
 */
abstract class DecoratorLogger implements Logger
{
    private Logger $decoratedLogger;

    public function __construct(Logger $decoratedLogger)
    {
        $this->decoratedLogger = $decoratedLogger;
    }

    public function logConfiguration(
        Configuration $configuration,
        int $batchSize,
        ?int $numberOfItems,
        string $itemName,
        bool $shouldSpawnChildProcesses
    ): void {
        $this->decoratedLogger->logConfiguration(...func_get_args());
    }

    public function logStart(?int $numberOfItems): void
    {
        $this->decoratedLogger->logStart(...func_get_args());
    }

    public function logAdvance(int $steps = 1): void
    {
        $this->decoratedLogger->logAdvance(...func_get_args());
    }

    public function logFinish(string $itemName): void
    {
        $this->decoratedLogger->logFinish(...func_get_args());
    }

    public function logItemProcessingFailed(string $item, Throwable $throwable): void
    {
        $this->decoratedLogger->logItemProcessingFailed(...func_get_args());
    }

    public function logChildProcessStarted(int $index, int $pid, string $commandName): void
    {
        $this->decoratedLogger->logChildProcessStarted(...func_get_args());
    }

    public function logChildProcessFinished(int $index): void
    {
        $this->decoratedLogger->logChildProcessFinished(...func_get_args());
    }

    public function logUnexpectedChildProcessOutput(
        int $index,
        ?int $pid,
        string $type,
        string $buffer,
        string $progressSymbol
    ): void {
        $this->decoratedLogger->logUnexpectedChildProcessOutput(...func_get_args());
    }
}
