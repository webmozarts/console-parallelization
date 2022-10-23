<?php

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
        Assert::true(is_int($size) || is_float($size));

        if (-1 === $size) {
            return '-1';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

        $power = $size > 0 ? (int) floor(log($size, 1024)) : 0;

        return sprintf(
            '%s%s',
            number_format(
                $size / (1024 ** $power),
                $decimals,
            ),
            $units[$power],
        );
    }
}
