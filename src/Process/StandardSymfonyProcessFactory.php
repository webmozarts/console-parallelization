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
use Symfony\Component\Process\PhpSubprocess;
use Symfony\Component\Process\Process;

final class StandardSymfonyProcessFactory implements SymfonyProcessFactory
{
    public function startProcess(
        int $index,
        InputStream $inputStream,
        array $phpExecutable,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $processOutput
    ): Process {
        $process = new PhpSubprocess(
            $command,
            $workingDirectory,
            $environmentVariables,
            php: $phpExecutable,
        );

        $process->setInput($inputStream);
        // @codeCoverageIgnoreStart
        $process->start(
            static fn (string $type, string $buffer) => $processOutput(
                $index,
                $process->getPid(),
                $type,
                $buffer,
            ),
        );
        // @codeCoverageIgnoreEnd

        return $process;
    }
}
