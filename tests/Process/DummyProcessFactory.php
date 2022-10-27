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
use Webmozarts\Console\Parallelization\BinomialSum;

final class DummyProcessFactory implements SymfonyProcessFactory
{
    private int $exitCodeIndex = 0;

    private int $pidSequence = 1000;

    /**
     * @var list<DummyProcess>
     */
    public array $processes = [];

    public function startProcess(
        int $index,
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $processOutput
    ): Process {
        $pid = $this->pidSequence;
        ++$this->pidSequence;

        $process = new DummyProcess(
            $index,
            $pid,
            $command,
            BinomialSum::A000079[$this->exitCodeIndex],
            $workingDirectory,
            $environmentVariables,
            $inputStream,
        );
        $this->processes[] = $process;

        $process->start($processOutput);

        ++$this->exitCodeIndex;

        return $process;
    }
}
