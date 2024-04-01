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

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use ReflectionClass;
use ReflectionProperty;
use Webmozarts\Console\Parallelization\Process\CpuCoreCounter;

final class ResetCpuCounterSubscriber implements PreparationStartedSubscriber
{
    private static ?ReflectionProperty $countReflection = null;

    public function notify(PreparationStarted $event): void
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
