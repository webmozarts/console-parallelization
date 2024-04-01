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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use function array_map;
use function range;
use function strval;

final class AbsoluteScriptPathCommand extends ParallelCommand
{
    public function __construct()
    {
        parent::__construct('absolute-script-path');
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return array_map(
            strval(...),
            range(0, 3),
        );
    }

    protected function configureParallelExecutableFactory(ParallelExecutorFactory $parallelExecutorFactory, InputInterface $input, OutputInterface $output): ParallelExecutorFactory
    {
        return $parallelExecutorFactory
            ->withBatchSize(2)
            ->withSegmentSize(2);
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        // Do nothing
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }
}
