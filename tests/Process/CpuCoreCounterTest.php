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
use ReflectionClass;
use Webmozarts\Console\Parallelization\EnvironmentVariables;

/**
 * @covers \Webmozarts\Console\Parallelization\Process\CpuCoreCounter
 *
 * @internal
 */
final class CpuCoreCounterTest extends TestCase
{
    protected function tearDown(): void
    {
        self::removeCachedCount();
    }

    /**
     * @backupGlobals
     */
    public function test_can_get_the_number_of_cpu_cores(): void
    {
        unset($_ENV['WEBMOZARTS_CONSOLE_PARALLELIZATION_CPU_COUNT']);

        $cpuCoresCount = CpuCoreCounter::getNumberOfCpuCores();

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

    private static function removeCachedCount(): void
    {
        $reflectionClass = new ReflectionClass(CpuCoreCounter::class);
        $countReflection = $reflectionClass->getProperty('count');
        $countReflection->setAccessible(true);
        $countReflection->setValue(new CpuCoreCounter(), null);
    }
}
