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
use Webmozarts\Console\Parallelization\Fixtures\ItemNamingCapabilities;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class NoItemCommand extends ParallelCommand
{
    use ItemNamingCapabilities;

    public function __construct()
    {
        parent::__construct('test:no-item');
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): iterable
    {
        return [];
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
