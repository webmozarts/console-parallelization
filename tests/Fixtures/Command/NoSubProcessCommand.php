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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ContainerAwareCommand;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\Parallelization;

final class NoSubProcessCommand extends ContainerAwareCommand
{
    use Parallelization;

    protected static $defaultName = 'test:no-subprocess';

    private bool $mainProcess = false;

    protected function configure(): void
    {
        self::configureParallelization($this);
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input): array
    {
        return [
            'item1',
            'item2',
            'item3',
            'item4',
            'item5',
        ];
    }

    protected function getSegmentSize(): int
    {
        return 2;
    }

    protected function runBeforeFirstCommand(InputInterface $input, OutputInterface $output): void
    {
        $this->mainProcess = true;
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        if (!$this->mainProcess) {
            throw new DomainException('Expected to be executed within the main process.');
        }
    }

    protected function getItemName(int $count): string
    {
        return 0 === $count ? 'item' : 'items';
    }

    protected function createLogger(OutputInterface $output): Logger
    {
        return new StandardLogger(
            $output,
            self::getProgressSymbol(),
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
            new ConsoleLogger($output),
        );
    }
}
