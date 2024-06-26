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

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webmozarts\Console\Parallelization\EnvironmentVariables;

/**
 * @internal
 */
#[CoversClass(CpuCoreCounter::class)]
final class CpuCoreCounterTest extends TestCase
{
    // Note that no teardown is necessary; we leverage the ResetCpuCounterSubscriber.
    #[BackupGlobals(true)]
    public function test_can_get_the_number_of_cpu_cores(): void
    {
        $cleanUp = EnvironmentVariables::setVariables([
            'WEBMOZARTS_CONSOLE_PARALLELIZATION_CPU_COUNT' => null,
        ]);

        $cpuCoresCount = CpuCoreCounter::getNumberOfCpuCores();

        $cleanUp();

        self::assertGreaterThan(0, $cpuCoresCount);
    }

    public function test_can_get_the_number_of_cpu_cores_defined(): void
    {
        $cleanUp = EnvironmentVariables::setVariables([
            'WEBMOZARTS_CONSOLE_PARALLELIZATION_CPU_COUNT' => '7',
        ]);

        $cpuCoresCount = CpuCoreCounter::getNumberOfCpuCores();

        $cleanUp();

        self::assertSame(7, $cpuCoresCount);
    }
}
