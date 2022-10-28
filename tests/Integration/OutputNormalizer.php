<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Integration;

use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\PhpExecutableFinder;
use function array_keys;
use function getcwd;
use function preg_replace;
use function sprintf;
use function str_replace;
use const PHP_EOL;

final class OutputNormalizer
{
    public static function normalizeMemoryUsage(string $output): string
    {
        return preg_replace(
            '/\d+(\.\d+)? ([A-Z]i)?B/',
            '10.0 MiB',
            $output,
        );
    }

    public static function normalizeProgressBarTimeTaken(string $output): string
    {
        $output = str_replace(
            '< 1 sec',
            '10 secs',
            $output,
        );

        $output = preg_replace(
            '/\d+ secs?/',
            '10 secs',
            $output,
        );

        $replaceMap = [
            '%  10 secs' => '% 10 secs',
            'secs  10.0 MiB' => 'secs 10.0 MiB',
            ']  10 secs' => '] 10 secs',
            PHP_EOL => "\n",
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output,
        );
    }

    public static function normalizePhpExecutablePath(string $output): string
    {
        $replaceMap = [
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        $output = self::normalizeConsolePath($output);

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output,
        );
    }

    public static function normalizeProjectPath(string $output): string
    {
        $replaceMap = [
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        $output = self::normalizeConsolePath($output);

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output,
        );
    }

    public static function normalizeLineReturns(string $output): string
    {
        $replaceMap = [
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        $output = self::normalizeConsolePath($output);

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output,
        );
    }

    private function getOutput(CommandTester $commandTester): string
    {
        $output = $commandTester->getDisplay(true);

//        $output = preg_replace(
//            '/\d+(\.\d+)? ([A-Z]i)?B/',
//            '10.0 MiB',
//            $output,
//        );

        $output = str_replace(
            '< 1 sec',
            '10 secs',
            $output,
        );

        $output = preg_replace(
            '/\d+ secs?/',
            '10 secs',
            $output,
        );

        $replaceMap = [
            '%  10 secs' => '% 10 secs',
            'secs  10.0 MiB' => 'secs 10.0 MiB',
            ']  10 secs' => '] 10 secs',
            PHP_EOL => "\n",
            (new PhpExecutableFinder())->find() => '/path/to/php',
            getcwd() => '/path/to/work-dir',
        ];

        $output = self::normalizeConsolePath($output);

        return str_replace(
            array_keys($replaceMap),
            $replaceMap,
            $output,
        );
    }

    private static function normalizeConsolePath(string $output): string
    {
        return preg_replace(
            '~'.getcwd().'.+?console~',
            '/path/to/work-dir/bin/console',
            $output,
        );
    }

    public static function removeIntermediateFixedProgressBars(
        string $output,
        int $expectedNumberOfItems = 5
    ): string {
        $intermediateItemRange = sprintf(
            '[1-%d]/%d',
            $expectedNumberOfItems - 1,
            $expectedNumberOfItems,
        );

        return preg_replace(
            '# *?'.$intermediateItemRange.' \[[=>-]+\]  \d+% 10 secs/10 secs 10.0 MiB\n#',
            '',
            $output,
        );
    }

    public static function removeIntermediateNonFixedProgressBars(string $output): string
    {
        $output = preg_replace(
            '# *?[1-4] \[[>-]+\]  ?10 secs 10.0 MiB\n#',
            '',
            $output,
        );

        return str_replace(
            '\[[->]+?\]',
            '[----->----------------------]',
            $output,
        );
    }

    private function __construct()
    {
    }
}
