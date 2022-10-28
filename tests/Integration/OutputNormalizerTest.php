<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\PhpExecutableFinder;
use Webmozarts\Console\Parallelization\EnvironmentVariables;
use function Safe\getcwd;

/**
 * @covers \Webmozarts\Console\Parallelization\Integration\OutputNormalizer
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
            ' 10 secs/10 secs ',
        ];

        yield '[fixed size bar] mixed' => [
            ' < 1 sec/18 secs',
            ' 10 secs/10 secs ',
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
        $environmentVariables = [
            'PHP_BINARY' => 'Users/myaccount/.phpbrew/php/php-9.3.2/bin/php',
        ];

        yield 'escaped path' => [
            ' \'/Users/myaccount/.phpbrew/php/php-9.3.2/bin/php\' ',
            $environmentVariables,
            ' \'/path/to/php\' ',
        ];

        yield 'non escaped-path' => [
            ' /Users/myaccount/.phpbrew/php/php-9.3.2/bin/php ',
            $environmentVariables,
            ' /path/to/php ',
        ];

        yield 'escaped path longer path' => [
            ' \'/Users/myaccount/.phpbrew/php/php-9.3.2/bin/php/phar\' ',
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
            ' \'/'.$cwd.'\' ',
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
            <<<'TXT'
            
            
            TXT,
            ' \'/path/to/work-dir\' ',
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
        string $expected
    ): void {
        $actual = OutputNormalizer::removeIntermediateNonFixedProgressBars($output);

        self::assertSame($expected, $actual);
    }

    public static function nonFixedSizedProgressBarsProvider(): iterable
    {
        yield 'single intermediate line' => [
            ' 4/5 [---------------------->-----]  80%  1 sec/1 sec  10.0 MiB',
            '',
        ];

        yield 'single intermediate line with trailing content' => [
            ' 4/5 [---------------------->-----]  80%  1 sec/1 sec  10.0 MiB [debug] smth',
            ' [debug] smth',
        ];

        yield 'nominal without extra content' => [
            <<<'TXT'
            
             0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
             1/5 [----->----------------------]  20% 2 secs/10 secs 10.0 MiB
             2/5 [----------->----------------]  40% 4 secs/10 secs 10.0 MiB
             3/5 [---------------->-----------]  60% 6 secs/10 secs 10.0 MiB
             4/5 [---------------------->-----]  80% 8 secs/10 secs 10.0 MiB
             5/5 [----------------------------] 100% 10 secs/10 secs 10.0 MiB

            TXT,
            <<<'TXT'
            
             0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
             5/5 [----------------------------] 100% 10 secs/10 secs 10.0 MiB

            TXT,
        ];

        yield 'nominal with extra content' => [
            <<<'TXT'
            
             0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
             1/5 [----->----------------------]  20% 2 secs/10 secs 10.0 MiB [debug] smth1
            [debug] smth2
             2/5 [----------->----------------]  40% 4 secs/10 secs 10.0 MiB
             3/5 [---------------->-----------]  60% 6 secs/10 secs 10.0 MiB[debug] smth3
             4/5 [---------------------->-----]  80% 8 secs/10 secs 10.0 MiB
             5/5 [----------------------------] 100% 10 secs/10 secs 10.0 MiB

            TXT,
            <<<'TXT'
            
             0/5 [>---------------------------]   0% < 1 sec/< 1 sec 8.0 MiB
             [debug] smth1
            [debug] smth2
            [debug] smth3
             5/5 [----------------------------] 100% 10 secs/10 secs 10.0 MiB

            TXT,
        ];
    }
}
