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

use PHPUnit\Framework\TestCase;
use Webmozarts\Console\Parallelization\EnvironmentVariables;
use function Safe\getcwd;

/**
 * @covers \Webmozarts\Console\Parallelization\Integration\OutputNormalizer
 *
 * @internal
 */
final class OutputNormalizerTest extends TestCase
{
    /**
     * @dataProvider memoryUsageProvider
     */
    public function test_it_can_normalize_the_memory_usage(string $output, string $expected): void
    {
        $actual = OutputNormalizer::normalizeMemoryUsage($output);

        self::assertSame($expected, $actual);
    }

    public static function memoryUsageProvider(): iterable
    {
        yield 'single digit' => [
            '8.0 MiB',
            '10.0 MiB',
        ];

        yield 'several digits' => [
            '231.0 MiB',
            '10.0 MiB',
        ];

        yield 'with spaces' => [
            ' 8.0 MiB ',
            ' 10.0 MiB ',
        ];

        yield 'fixed size bar' => [
            ' 4/5 [======================>-----]  80%  1 sec/1 sec  8.0 MiB ',
            ' 4/5 [======================>-----]  80%  1 sec/1 sec  10.0 MiB ',
        ];

        yield 'non-fixed size bar' => [
            ' 5 [----->----------------------] < 1 sec 8.0 MiB ',
            ' 5 [----->----------------------] < 1 sec 10.0 MiB ',
        ];

        yield 'MB' => [
            '8.0 MB',
            '10.0 MB',
        ];

        yield 'with NBSP' => [
            "8.0\u{a0}MB",
            '10.0 MB',
        ];
    }

    /**
     * @dataProvider progressBarTimeTakenProvider
     */
    public function test_it_can_normalize_the_time_taken_for_a_progress_bar(
        string $output,
        string $expected
    ): void {
        $actual = OutputNormalizer::normalizeProgressBarTimeTaken($output);

        self::assertSame($expected, $actual);
    }

    public static function progressBarTimeTakenProvider(): iterable
    {
        yield '[non-fixed size bar] less than a second' => [
            ' < 1 sec ',
            ' 10 secs ',
        ];

        yield '[non-fixed size bar] single digit' => [
            ' 7 secs ',
            ' 10 secs ',
        ];

        yield '[non-fixed size bar] multiple digits' => [
            ' 17 secs ',
            ' 10 secs ',
        ];

        yield '[fixed size bar] less than a second' => [
            ' < 1 sec/< 1 sec',
            ' 10 secs/10 secs',
        ];

        yield '[fixed size bar] single digit' => [
            ' 4 secs/7 secs',
            ' 10 secs/10 secs',
        ];

        yield '[fixed size bar] mixed' => [
            ' < 1 sec/18 secs',
            ' 10 secs/10 secs',
        ];

        yield 'fixed size bar' => [
            ' 4/5 [======================>-----]  80%  4 secs/10 secs  8.0 MiB ',
            ' 4/5 [======================>-----]  80%  10 secs/10 secs  8.0 MiB ',
        ];

        yield 'non-fixed size bar' => [
            ' 5 [----->----------------------] 4 secs 8.0 MiB ',
            ' 5 [----->----------------------] 10 secs 8.0 MiB ',
        ];
    }

    /**
     * @dataProvider phpExecutableProvider
     */
    public function test_it_can_normalize_the_php_executable_path(
        string $output,
        array $environmentVariables,
        string $expected
    ): void {
        $cleanup = EnvironmentVariables::setVariables($environmentVariables);

        $actual = OutputNormalizer::normalizePhpExecutablePath($output);

        $cleanup();

        self::assertSame($expected, $actual);
    }

    public static function phpExecutableProvider(): iterable
    {
        $phpBinary = __DIR__.'/php-dummy';
        $environmentVariables = ['PHP_BINARY' => $phpBinary];

        yield 'escaped path' => [
            ' \''.$phpBinary.'\' ',
            $environmentVariables,
            ' \'/path/to/php\' ',
        ];

        yield 'non escaped-path' => [
            ' '.$phpBinary.' ',
            $environmentVariables,
            ' /path/to/php ',
        ];

        yield 'escaped path longer path' => [
            ' \''.$phpBinary.'/phar\' ',
            $environmentVariables,
            ' \'/path/to/php/phar\' ',
        ];
    }

    /**
     * @dataProvider normalizePathProvider
     */
    public function test_it_can_normalize_the_project_path(
        string $output,
        string $expected
    ): void {
        $actual = OutputNormalizer::normalizeProjectPath($output);

        self::assertSame($expected, $actual);
    }

    public static function normalizePathProvider(): iterable
    {
        $cwd = getcwd();

        yield 'escaped path' => [
            ' \''.$cwd.'\' ',
            ' \'/path/to/work-dir\' ',
        ];

        yield 'non escaped-path' => [
            ' '.$cwd.' ',
            ' /path/to/work-dir ',
        ];

        yield 'escaped path longer path' => [
            ' \''.$cwd.'/bin/foo.php\' ',
            ' \'/path/to/work-dir/bin/foo.php\' ',
        ];
    }

    /**
     * @dataProvider lineReturnsProvider
     */
    public function test_it_can_normalize_line_returns(
        string $output,
        string $expected
    ): void {
        $actual = OutputNormalizer::normalizeLineReturns($output);

        self::assertSame($expected, $actual);
    }

    public static function lineReturnsProvider(): iterable
    {
        yield 'linux return paths' => [
            "\n \n",
            "\n \n",
        ];

        yield 'windows return paths' => [
            "\r\n  \r\n",
            "\n  \n",
        ];
    }

    /**
     * @dataProvider fixedSizedProgressBarsProvider
     */
    public function test_it_can_normalize_fixed_sized_progress_bars(
        string $output,
        string $expected
    ): void {
        $actual = OutputNormalizer::removeIntermediateFixedProgressBars($output);

        self::assertSame($expected, $actual);
    }

    public static function fixedSizedProgressBarsProvider(): iterable
    {
        yield 'single intermediate line' => [
            ' 4/5 [======================>-----]  80%  1 sec/1 sec  10.0 MiB',
            '',
        ];

        yield 'single intermediate line with trailing content' => [
            ' 4/5 [======================>-----]  80%  1 sec/1 sec  10.0 MiB [debug] smth',
            ' [debug] smth',
        ];

        yield 'nominal without extra content' => [
            <<<'TXT'

                 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
                 1/5 [=====>----------------------]  20% 2 secs/10 secs 10.0 MiB
                 2/5 [===========>----------------]  40% 4 secs/10 secs 10.0 MiB
                 3/5 [================>-----------]  60% 6 secs/10 secs 10.0 MiB
                 4/5 [======================>-----]  80% 8 secs/10 secs 10.0 MiB
                 5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

                TXT,
            <<<'TXT'

                 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
                 5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

                TXT,
        ];

        yield 'nominal with extra content' => [
            <<<'TXT'

                 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
                 1/5 [=====>----------------------]  20% 2 secs/10 secs 10.0 MiB [debug] smth1
                [debug] smth2
                 2/5 [===========>----------------]  40% 4 secs/10 secs 10.0 MiB
                 3/5 [================>-----------]  60% 6 secs/10 secs 10.0 MiB[debug] smth3
                 4/5 [======================>-----]  80% 8 secs/10 secs 10.0 MiB
                 5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

                TXT,
            <<<'TXT'

                 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
                 [debug] smth1
                [debug] smth2
                [debug] smth3
                 5/5 [============================] 100% 10 secs/10 secs 10.0 MiB

                TXT,
        ];
    }

    /**
     * @dataProvider nonFixedSizedProgressBarsProvider
     */
    public function test_it_can_normalize_non_fixed_sized_progress_bars(
        string $output,
        int $numberOfItems,
        string $expected
    ): void {
        $actual = OutputNormalizer::removeIntermediateNonFixedProgressBars(
            $output,
            $numberOfItems,
        );

        self::assertSame($expected, $actual);
    }

    public static function nonFixedSizedProgressBarsProvider(): iterable
    {
        yield 'single intermediate line' => [
            ' 3 [---------------------->-----] 4 secs  7.0 MiB',
            0,
            '',
        ];

        yield 'single intermediate line with trailing content' => [
            ' 3 [---------------------->-----] 4 secs  7.0 MiB [debug] smth',
            0,
            ' [debug] smth',
        ];

        yield 'single intermediate line with trailing content without spacing' => [
            ' 3 [---------------------->-----] 4 secs  7.0 MiB[debug] smth',
            0,
            '[debug] smth',
        ];

        yield 'nominal without extra content' => [
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 1 [----->----------------------] 2 secs 10.0 MiB
                 5 [----------->----------------] 4 secs 10.0 MiB
                 6 [---------------->-----------] 6 secs 10.0 MiB
                 9 [---------------------->-----] 8 secs 10.0 MiB
                 12 [----------------------------] 142 secs 112.0 MiB

                TXT,
            12,
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 12 [----->----------------------] 142 secs 112.0 MiB

                TXT,
        ];

        yield 'nominal without extra content with the cursor back to the start' => [
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 1 [----->----------------------] 2 secs 10.0 MiB
                 5 [----------->----------------] 4 secs 10.0 MiB
                 6 [---------------->-----------] 6 secs 10.0 MiB
                 9 [---------------------->-----] 8 secs 10.0 MiB
                 12 [>---------------------------] 142 secs 112.0 MiB

                TXT,
            12,
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 12 [----->----------------------] 142 secs 112.0 MiB

                TXT,
        ];

        yield 'nominal without extra content with the cursor in the middle' => [
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 1 [----->----------------------] 2 secs 10.0 MiB
                 5 [----------->----------------] 4 secs 10.0 MiB
                 6 [---------------->-----------] 6 secs 10.0 MiB
                 9 [---------------------->-----] 8 secs 10.0 MiB
                 12 [------------>---------------] 142 secs 112.0 MiB

                TXT,
            12,
            <<<'TXT'

                 0 [>---------------------------] < 1 sec 8.0 MiB
                 12 [----->----------------------] 142 secs 112.0 MiB

                TXT,
        ];

        yield 'buggy case #1' => [
            <<<'TXT'

                    0 [>---------------------------] 10 secs 10.0 MiB
                    2 [->--------------------------]  10 secs 10.0 MiB
                    4 [--->------------------------]  10 secs 10.0 MiB
                    5 [----->----------------------]  10 secs 10.0 MiB

                TXT,
            5,
            <<<'TXT'

                    0 [>---------------------------] 10 secs 10.0 MiB
                    5 [----->----------------------]  10 secs 10.0 MiB

                TXT,
        ];

        // https://github.com/webmozarts/console-parallelization/actions/runs/3418534317/jobs/5690951308
        yield 'final step at a random stage' => [
            <<<'TXT'

                    0 [>---------------------------] 10 secs 10.0 MiB
                    5 [------->--------------------] 10 secs 10.0 MiB

                TXT,
            5,
            <<<'TXT'

                    0 [>---------------------------] 10 secs 10.0 MiB
                    5 [----->----------------------] 10 secs 10.0 MiB

                TXT,
        ];
    }

    /**
     * @dataProvider outputProvider
     */
    public function test_it_can_normalize_output(string $output, string $expected): void
    {
        $actual = OutputNormalizer::normalize($output);

        self::assertSame($expected, $actual);
    }

    public static function outputProvider(): iterable
    {
        yield 'standard non-fixed sized progress bar line' => [
            ' 4 [--->------------------------] < 1 sec 8.0 MiB',
            ' 4 [--->------------------------] 10 secs 10.0 MiB',
        ];

        yield 'standard non-fixed sized progress bar line with extra padding' => [
            '     4 [--->------------------------] < 1 sec 8.0 MiB',
            '     4 [--->------------------------] 10 secs 10.0 MiB',
        ];

        yield 'standard non-fixed sized progress bar line with extra content no spacing' => [
            ' 4 [--->------------------------] < 1 sec 8.0 MiB[debug] Command started:',
            ' 4 [--->------------------------] 10 secs 10.0 MiB[debug] Command started:',
        ];

        yield 'standard non-fixed sized progress bar line with extra content with spacing' => [
            ' 4 [--->------------------------] < 1 sec 8.0 MiB [debug] Command started:',
            ' 4 [--->------------------------] 10 secs 10.0 MiB [debug] Command started:',
        ];

        yield 'standard fixed sized progress bar line' => [
            ' 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB',
            ' 0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB',
        ];

        yield 'standard fixed sized progress bar line with extra padding' => [
            '     0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB',
            '     0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB',
        ];

        yield 'standard fixed sized progress bar line with extra content no spacing' => [
            ' 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB[debug] Command started:',
            ' 0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB[debug] Command started:',
        ];

        yield 'fixed sized progress bar line with extra content with spacing' => [
            ' 0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB [debug] Command started:',
            ' 0/5 [>---------------------------]   0% 10 secs/10 secs 10.0 MiB [debug] Command started:',
        ];

        yield 'fixed sized line with extra spacing' => [
            ' 5/5 [============================] 100%  1 sec/1 sec  14.0 MiB[debug] Command finished',
            ' 5/5 [============================] 100% 10 secs/10 secs 10.0 MiB[debug] Command finished',
        ];

        yield 'non fixed sized line with extra spacing' => [
            ' 5 [----->----------------------]  10 secs 10.0 MiB',
            ' 5 [----->----------------------] 10 secs 10.0 MiB',
        ];
    }
}
