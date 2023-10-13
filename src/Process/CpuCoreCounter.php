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

use Fidry\CpuCoreCounter\CpuCoreCounter as FidryCpuCoreCounter;
use Fidry\CpuCoreCounter\NumberOfCpuCoreNotFound;
use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class CpuCoreCounter
{
    /**
     * @var positive-int|null
     */
    private static ?int $count = null;

    /**
     * @return positive-int
     */
    public static function getNumberOfCpuCores(): int
    {
        if (null !== self::$count) {
            return self::$count;
        }

        $count = $_ENV['WEBMOZARTS_CONSOLE_PARALLELIZATION_CPU_COUNT'] ?? false;

        if (false !== $count) {
            Assert::numeric($count);
            Assert::positiveInteger((int) $count);

            return self::$count = (int) $count;
        }

        try {
            self::$count = (new FidryCpuCoreCounter())->getCount();
        } catch (NumberOfCpuCoreNotFound) {
            self::$count = 1;
        }

        return self::$count;
    }
}
