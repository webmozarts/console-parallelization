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
use function preg_replace;
use function spl_object_id;
use function str_replace;

/**
 * @internal
 */
#[CoversNothing]
class PhpProcessSettingsTest extends TestCase
{
    public function test_it_can_run_the_command_without_sub_processes(): void
    {
        $commandProcess = Process::fromShellCommandline(
            'php -dmemory_limit="256M" bin/console test:php-settings',
            __DIR__.'/../..',
        );
        $commandProcess->run();

        $expectedMainProcessMemoryLimit = '256M';
        $actualMainProcessMemoryLimit = file_get_contents(PhpSettingsCommand::OUTPUT_DIR.'_main_process');

        $expectedChildProcessMemoryLimit = '256M';
        $actualChildProcessMemoryLimit = file_get_contents(PhpSettingsCommand::OUTPUT_DIR);

        self::assertSame(
            [
                $expectedMainProcessMemoryLimit,
                $expectedChildProcessMemoryLimit,
            ],
            [
                $actualMainProcessMemoryLimit,
                $actualChildProcessMemoryLimit,
            ],
        );
    }
}
