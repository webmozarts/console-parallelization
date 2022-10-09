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
use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\Input\IO;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use function realpath;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use Webmozarts\Console\Parallelization\Parallelization;

final class NoSubProcessCommand implements Command
{
    use Parallelization {
        getParallelExecutableFactory as getOriginalParallelExecutableFactory;
    }

    private bool $mainProcess = false;

    public function getName(): string
    {
        return 'test:no-subprocess';
    }

    public function getConfiguration(): Configuration
    {
        return ParallelizationInput::createConfiguration(
            $this->getName(),
            '',
            '',
        );
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(IO $io): array
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
        ItemProcessingErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        return $this
            ->getOriginalParallelExecutableFactory(
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
                function () {
                    $this->mainProcess = true;
                },
            )
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    protected function runSingleCommand(string $item, IO $io): void
    {
        if (!$this->mainProcess) {
            throw new DomainException('Expected to be executed within the main process.');
        }
    }

    protected function getItemName(int $count): string
    {
        return 0 === $count ? 'item' : 'items';
    }

    protected function createLogger(IO $io): Logger
    {
        return new StandardLogger(
            $io,
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
            new ConsoleLogger($io->getOutput()),
        );
    }
}
