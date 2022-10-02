<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Process;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use function method_exists;

interface SymfonyProcessFactory
{
    /**
     * Starts a single process reading from the given input stream.
     */
    public function startProcess(
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        array $environmentVariables,
        callable $callback
    ): Process;
}
