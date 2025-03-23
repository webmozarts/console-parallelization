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
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use Webmozarts\Console\Parallelization\Parallelization;
use function file_get_contents;
use function ini_get;
use function json_decode;
use function realpath;
use const JSON_THROW_ON_ERROR;

final class PhpSettingsCommand extends Command
{
    use Parallelization;

    public const OUTPUT_DIR = __DIR__.'/../../../dist/php-settings';

    public function __construct(
        private Filesystem $filesystem,
    ) {
        parent::__construct('test:php-settings');
    }

    protected function configure(): void
    {
        ParallelizationInput::configureCommand($this);
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
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $this->filesystem->dumpFile(
            self::OUTPUT_DIR,
            ini_get('memory_limit'),
        );
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }
}
