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
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class FakeProcessFactory implements SymfonyProcessFactory
{
    public function startProcess(
        int $index,
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $processOutput
    ): Process {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
