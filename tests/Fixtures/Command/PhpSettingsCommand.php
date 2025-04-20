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

namespace Webmozarts\Console\Parallelization\Fixtures\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Filesystem\Filesystem;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Integration\TestLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\NullLogger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use Webmozarts\Console\Parallelization\Parallelization;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use function file_get_contents;
use function ini_get;
use function json_decode;
use function realpath;
use function sprintf;
use function xdebug_break;
use const JSON_THROW_ON_ERROR;

final class PhpSettingsCommand extends ParallelCommand
{
    public const string MAIN_PROCESS_OUTPUT_DIR = __DIR__.'/../../../dist/php-settings_main-process';
    public const string CHILD_PROCESS_OUTPUT_DIR = __DIR__.'/../../../dist/php-settings_child-process';

    public function __construct(
        private Filesystem $filesystem,
    ) {
        parent::__construct('test:php-settings');
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return ['item0'];
    }

    protected function getParallelExecutableFactory(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        return ParallelExecutorFactory::create(
            $fetchItems,
            $runSingleCommand,
            $getItemName,
            $commandName,
            $commandDefinition,
            $errorHandler,
        )
            ->withRunBeforeFirstCommand(self::runBeforeFirstCommand(...))
            ->withPhpExecutable([
                ...PhpExecutableFinder::find(),
                '-dmax_input_time=30',
            ])
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    private function runBeforeFirstCommand(): void
    {
        $this->dumpMemoryLimit(self::MAIN_PROCESS_OUTPUT_DIR);
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $this->dumpMemoryLimit(self::CHILD_PROCESS_OUTPUT_DIR);
    }

    private function dumpMemoryLimit(string $filePath): void
    {
        $this->filesystem->dumpFile(
            $filePath,
            sprintf(
                'memory_limit=%s%smax_input_time=%s',
                ini_get('memory_limit'),
                "\n",
                ini_get('max_input_time'),
            ),
        );
    }

    public static function createSettingsOutput(string $memoryLimit, string $maxInputTime): string
    {
        return sprintf(
            'memory_limit=%s%smax_input_time=%s',
            $memoryLimit,
            "\n",
            $maxInputTime,
        );
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }
}
