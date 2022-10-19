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

final class FrameworkLessCommand extends ParallelCommand
{
    public function __construct()
    {
        parent::__construct('test:no-framework');
    }

    protected function fetchItems(InputInterface $input): iterable
    {
        return ['item0', 'item1'];
    }

    protected function runSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    protected function getItemName(?int $count): string
    {
        return 'uncountable';
    }
}
