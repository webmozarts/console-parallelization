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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;
use Webmozarts\Console\Parallelization\FakeCallable;

/**
 * @covers \Webmozarts\Console\Parallelization\Process\StandardSymfonyProcessFactory
 */
final class StandardSymfonyProcessFactoryTest extends TestCase
{
    public function test_it_can_create_a_configured_process(): void
    {
        $factory = new StandardSymfonyProcessFactory();

        $inputStream = new InputStream();
        $command = ['php', 'echo.php'];
        $workingDirectory = __DIR__;
        $environmentVariables = ['TEST_PARALLEL' => '0'];
        $callback = FakeCallable::create();

        $process = $factory->startProcess(
            $inputStream,
            $command,
            $workingDirectory,
            $environmentVariables,
            $callback,
        );

        self::assertSame("'php' 'echo.php'", $process->getCommandLine());
        self::assertSame($workingDirectory, $process->getWorkingDirectory());
        self::assertSame($environmentVariables, $process->getEnv());
    }
}
