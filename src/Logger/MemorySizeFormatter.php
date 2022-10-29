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

namespace Webmozarts\Console\Parallelization\Logger;

use Webmozart\Assert\Assert;
use function floor;
use function is_float;
use function is_int;
use function log;
use function number_format;
use function sprintf;

/**
 * Copied from humbug/box src/functions.php format_size().
 *
 * @internal
 */
final class MemorySizeFormatter
{
    /**
     * @param float|int $size
     */
    public static function format($size, int $decimals = 2): string
    {
        if (-1 === $size) {
            return '-1';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;

        return sprintf(
            "%s\u{a0}%s",
            number_format(
                $size / (1024 ** $power),
                $decimals,
            ),
            $units[$power],
        );
    }
}
