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

namespace Webmozarts\Console\Parallelization;

use Fidry\CpuCounter\CpuCoreCounter as FidryCpuCoreCounter;
use Fidry\CpuCounter\NumberOfCpuCoreNotFound;
use Webmozart\Assert\Assert;
use function getenv;

/**
 * @internal
 * From https://github.com/phpstan/phpstan-src/blob/1.8.x/src/Process/CpuCoreCounter.php
 */
final class CpuCoreCounter
{
    private static ?int $count = null;

    /**
     * @return positive-int
     */
    public static function getNumberOfCpuCores(): int
    {
        if (null !== self::$count) {
            return self::$count;
        }

        $count = getenv('WEBMOZARTS_CONSOLE_PARALLELIZATION_CPU_COUNT');

        if (false !== $count) {
            Assert::numeric($count);
            Assert::positiveInteger((int) $count);

            return self::$count = (int) $count;
        }

        try {
            self::$count = (new FidryCpuCoreCounter())->getCount();
        } catch (NumberOfCpuCoreNotFound $exception) {
            self::$count = 1;
        }

        return self::$count;
    }
}
