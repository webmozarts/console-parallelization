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

namespace Webmozarts\Console\Parallelization\PHPUnit;

use PHPUnit\Runner\AfterTestHook;
use ReflectionClass;
use ReflectionProperty;
use Webmozarts\Console\Parallelization\Process\CpuCoreCounter;

final class ResetCpuCounterListener implements AfterTestHook
{
    private static ?ReflectionProperty $countReflection = null;

    public function executeAfterTest(string $test, float $time): void
    {
        self::resetCounter();
    }

    private static function getCountReflection(): ReflectionProperty
    {
        if (!isset(self::$countReflection)) {
            self::$countReflection = (new ReflectionClass(CpuCoreCounter::class))->getProperty('count');
            self::$countReflection->setAccessible(true);
        }

        return self::$countReflection;
    }

    private static function resetCounter(): void
    {
        $countReflection = self::getCountReflection();
        $countReflection->setValue(new CpuCoreCounter(), null);
    }
}
