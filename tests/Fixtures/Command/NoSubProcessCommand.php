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

use DomainException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use Webmozarts\Console\Parallelization\Parallelization;
use function realpath;

final class NoSubProcessCommand extends Command
{
    use Parallelization;

    protected static $defaultName = 'test:no-subprocess';

    private bool $mainProcess = false;

    protected function configure(): void
    {
        ParallelizationInput::configureCommand($this);
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return [
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
        ];
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
            ->withBatchSize(2)
            ->withSegmentSize(2)
            ->withRunBeforeFirstCommand(
                function (): void {
                    $this->mainProcess = true;
                },
            )
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        if (!$this->mainProcess) {
            throw new DomainException('Expected to be executed within the main process.');
        }
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }

    protected function createLogger(
        InputInterface $input,
        OutputInterface $output
    ): Logger
    {
        return new StandardLogger(
            $input,
            $output,
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
        );
    }
}
