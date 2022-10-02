<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Process;

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class DummyProcessFactory implements SymfonyProcessFactory
{
    /**
     * @var list<DummyProcess>
     */
    public array $processes = [];

    public function startProcess(
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $callback
    ): Process {
        $process = new DummyProcess(
            $command,
            $workingDirectory,
            $environmentVariables,
            $inputStream,
        );
        $this->processes[] = $process;

        $process->start($callback);

        return $process;
    }
}
