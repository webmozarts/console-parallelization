<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Process;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use function method_exists;

final class StandardSymfonyProcessFactory implements SymfonyProcessFactory
{
    public function startProcess(
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        array $environmentVariables,
        callable $callback
    ): Process
    {
        $process = new Process(...[
            $command,
            $workingDirectory,
            $environmentVariables,
            null,
            null,
        ]);

        $process->setInput($inputStream);
        // TODO: remove the following once dropping Symfony 4.4. Environment
        //  variables are always inherited as of 5.0
        if (method_exists($process, 'inheritEnvironmentVariables')) {
            $process->inheritEnvironmentVariables(true);
        }
        $process->start($callback);

        return $process;
    }
}
