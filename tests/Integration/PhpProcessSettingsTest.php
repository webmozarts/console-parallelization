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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Webmozarts\Console\Parallelization\Fixtures\Command\ImportMoviesCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\ImportUnknownMoviesCountCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\LegacyCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\NoSubProcessCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\PhpSettingsCommand;
use Webmozarts\Console\Parallelization\Integration\BareKernel;
use Webmozarts\Console\Parallelization\Integration\OutputNormalizer;
use Webmozarts\Console\Parallelization\Integration\TestLogger;
use function array_column;
use function array_map;
use function file_get_contents;
use function ini_get;
use function preg_replace;
use function spl_object_id;
use function str_replace;

/**
 * @internal
 */
#[CoversNothing]
class PhpProcessSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        self::cleanupOutputFiles();
    }

    protected function tearDown(): void
    {
        self::cleanupOutputFiles();
    }

    public function test_it_can_run_the_command_setting_the_memory_limit(): void
    {
        $commandProcess = Process::fromShellCommandline(
            'php -dmemory_limit="256M" bin/console test:php-settings',
            __DIR__.'/../..',
            ['XDEBUG_SESSION' => '1', 'XDEBUG_MODE' => 'debug'],
        );
        $commandProcess->run();

        self::assertTrue(
            $commandProcess->isSuccessful(),
            $commandProcess->getOutput() . $commandProcess->getErrorOutput(),
        );

        $expectedMainProcessPhpSettings = PhpSettingsCommand::createSettingsOutput(
            '256M', // comes from setting it when launching the command
            ini_get('max_input_time'),
        );
        $actualMainProcessPhpSettings = file_get_contents(PhpSettingsCommand::MAIN_PROCESS_OUTPUT_DIR);

        $expectedChildProcessPhpSettings = PhpSettingsCommand::createSettingsOutput(
            '256M',
            '30',   // comes from PhpSettingsCommand specifying it in the config
        );
        $actualChildProcessPhpSettings = file_get_contents(PhpSettingsCommand::CHILD_PROCESS_OUTPUT_DIR);

        self::assertSame(
            [
                'main' => $expectedMainProcessPhpSettings,
                'child' => $expectedChildProcessPhpSettings,
            ],
            [
                'main' => $actualMainProcessPhpSettings,
                'child' => $actualChildProcessPhpSettings,
            ],
        );
    }

    public function test_it_can_run_the_command_setting_the_a_php_setting_configured_in_the_command(): void
    {
        $commandProcess = Process::fromShellCommandline(
            'php -dmemory_limit="256M" -dmax_input_time=45 bin/console test:php-settings',
            __DIR__.'/../..',
            ['XDEBUG_SESSION' => '1', 'XDEBUG_MODE' => 'debug'],
        );
        $commandProcess->run();

        self::assertTrue(
            $commandProcess->isSuccessful(),
            $commandProcess->getOutput() . $commandProcess->getErrorOutput(),
        );

        $expectedMainProcessPhpSettings = PhpSettingsCommand::createSettingsOutput(
            '256M',  // comes from setting it when launching the command
            '45',   // comes from setting it when launching the command
        );
        $actualMainProcessPhpSettings = file_get_contents(PhpSettingsCommand::MAIN_PROCESS_OUTPUT_DIR);

        $expectedChildProcessPhpSettings = PhpSettingsCommand::createSettingsOutput(
            '256M',
            '30',   // comes from PhpSettingsCommand specifying it in the config
        );
        $actualChildProcessPhpSettings = file_get_contents(PhpSettingsCommand::CHILD_PROCESS_OUTPUT_DIR);

        self::assertSame(
            [
                'main' => $expectedMainProcessPhpSettings,
                'child' => $expectedChildProcessPhpSettings,
            ],
            [
                'main' => $actualMainProcessPhpSettings,
                'child' => $actualChildProcessPhpSettings,
            ],
        );
    }

    private static function cleanupOutputFiles(): void
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove(PhpSettingsCommand::MAIN_PROCESS_OUTPUT_DIR);
        $fileSystem->remove(PhpSettingsCommand::CHILD_PROCESS_OUTPUT_DIR);
    }
}
