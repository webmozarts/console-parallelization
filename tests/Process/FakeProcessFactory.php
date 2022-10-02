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

use DomainException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class FakeProcessFactory implements SymfonyProcessFactory
{
    public function startProcess(
        InputStream $inputStream,
        array $command,
        string $workingDirectory,
        ?array $environmentVariables,
        callable $callback
    ): Process {
        throw new DomainException('Unexpected call.');
    }
}
