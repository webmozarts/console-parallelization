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

namespace Webmozarts\Console\Parallelization\Process;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type ProcessOutput callable(positive-int|0, int|null, string, string): void
 */
interface SymfonyProcessFactory
{
    /**
     * Starts a single process reading from the given input stream.
     *
     * @param positive-int|0             $index                Index of the process amoung the
     *                                                         list of running processes.
     * @param list<string>               $command
     * @param array<string, string>|null $environmentVariables
     * @param ProcessOutput              $processOutput        A PHP callback which is run whenever
     *                                                         there is some output available on
     *                                                         STDOUT or STDERR.
     */
    public function startProcess(
        int $index,
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $processOutput
    ): Process;
}
