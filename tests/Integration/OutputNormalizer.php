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

namespace Webmozarts\Console\Parallelization\Integration;

use Symfony\Component\Process\PhpExecutableFinder;
use function array_keys;
use function array_map;
use function array_values;
use function bin2hex;
use function explode;
use function getcwd;
use function implode;
use function preg_match;
use function preg_replace;
use function random_bytes;
use function str_replace;

final class OutputNormalizer
{
    public static function normalize(string $output): string
    {
        $output = self::removeTrailingSpaces(
            self::normalizeProgressBarTimeTaken(
                self::normalizeMemoryUsage(
                    self::normalizeProjectPath(
                        self::normalizePhpExecutablePath(
                            self::normalizeLineReturns($output),
                        ),
                    ),
                ),
            ),
        );

        // We may still have some unstable cases due to extra spacing added
        // depending on the overall result
        $replaceMap = [
            '%  10 secs' => '% 10 secs',
            '%    10 secs' => '% 10 secs',
            'secs  10.0 MiB' => 'secs 10.0 MiB',
            'secs    10.0 MiB' => 'secs 10.0 MiB',
            ']  10 secs' => '] 10 secs',
            ']    10 secs' => '] 10 secs',
        ];

        return str_replace(
            array_keys($replaceMap),
            array_values($replaceMap),
            $output,
        );
    }

    public static function normalizeMemoryUsage(string $output): string
    {
        return preg_replace(
            '/\d+(?:\.\d+)?(?:\x{00A0}|\s)(?:[A-Z])?(i?)B/u',
            '10.0 M$1B',
            $output,
        );
    }

    public static function normalizeProgressBarTimeTaken(string $output): string
    {
        $output = str_replace(
            ['< 1 sec', '< 1 ms'],
            ['10 secs', '10 secs'],
            $output,
        );

        return preg_replace(
            '/\d+ (secs?|ms|s)/',
            '10 secs',
            $output,
        );
    }

    public static function normalizePhpExecutablePath(string $output): string
    {
        return str_replace(
            (new PhpExecutableFinder())->find(),
            '/path/to/php',
            $output,
        );
    }

    public static function normalizeProjectPath(string $output): string
    {
        return str_replace(
            getcwd(),
            '/path/to/work-dir',
            $output,
        );
    }

    public static function normalizeLineReturns(string $output): string
    {
        return str_replace(
            "\r\n",
            "\n",
            $output,
        );
    }

    public static function removeTrailingSpaces(string $output): string
    {
        $lines = explode("\n", $output);

        return implode(
            "\n",
            array_map('rtrim', $lines),
        );
    }

    public static function removeIntermediateFixedProgressBars(string $output): string
    {
        $output = preg_replace(
            '# \d+\/\d+ \[=[=>-]+\-] .+?MiB\n#',
            '',
            $output,
        );

        return preg_replace(
            '# \d+\/\d+ \[=[=>-]+\-] .+?MiB#',
            '',
            $output,
        );
    }

    public static function removeIntermediateNonFixedProgressBars(
        string $output,
        int $itemsCount
    ): string {
        $restoreFirstLine = self::excludeNonFixedSizedProgressBarLine(
            $output,
            0,
        );
        $restoreLastLine = self::excludeNonFixedSizedProgressBarLine(
            $output,
            $itemsCount,
        );

        // Remove intermediate lines
        $output = preg_replace(
            '#\s+\d+ \[[->]+?\] .+?MiB\n#',
            '',
            $output,
        );
        $output = preg_replace(
            '#\s+\d+ \[[->]+?\] .+?MiB#',
            '',
            $output,
        );

        $output = $restoreFirstLine($restoreLastLine($output));

        // Normalize last line
        return preg_replace(
            '#(\s+'.$itemsCount.' )\[[->]+?\]( .+?MiB)#',
            '$1[----->----------------------]$2',
            $output,
        );
    }

    /**
     * @return callable(string):string
     */
    private static function excludeNonFixedSizedProgressBarLine(
        string &$output,
        int $itemNumber
    ): callable {
        $lineFound = 1 === preg_match(
            '#(?<line> '.$itemNumber.' \[[->]+] .+?MiB.*?\n?)#',
            $output,
            $matches,
        );

        if (!$lineFound) {
            return static fn (string $updatedOutput) => $updatedOutput;
        }

        $line = $matches['line'];
        $linePlaceholder = '__LAST_LINE_'.bin2hex(random_bytes(20)).'__';

        $output = str_replace($line, $linePlaceholder, $output);

        return static fn (string $updatedOutput) => str_replace($linePlaceholder, $line, $updatedOutput);
    }

    private function __construct()
    {
    }
}
