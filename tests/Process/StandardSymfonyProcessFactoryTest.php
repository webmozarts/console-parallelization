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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\InputStream;

/**
 * @internal
 */
#[CoversClass(StandardSymfonyProcessFactory::class)]
final class StandardSymfonyProcessFactoryTest extends TestCase
{
    public function test_it_can_create_a_configured_process(): void
    {
        $factory = new StandardSymfonyProcessFactory();

        $index = 11;
        $inputStream = new InputStream();
        $command = ['php', 'echo.php'];
        $workingDirectory = __DIR__;
        $environmentVariables = ['TEST_PARALLEL' => '0'];

        $processOutputCalled = false;

        // Do not use a Fake callback here as it would otherwise throw an
        // exception at a random time during cleanup.
        $processOutput = static function () use (&$processOutputCalled): void {
            $processOutputCalled = true;
        };

        $process = $factory->startProcess(
            $index,
            $inputStream,
            $command,
            $workingDirectory,
            $environmentVariables,
            $processOutput,
        );

        self::assertSame("'php' 'echo.php'", $process->getCommandLine());
        self::assertSame($workingDirectory, $process->getWorkingDirectory());
        self::assertSame($environmentVariables, $process->getEnv());
        self::assertTrue($process->isRunning());
        self::assertNotNull($process->getPid());
        self::assertFalse($processOutputCalled);
    }
}
