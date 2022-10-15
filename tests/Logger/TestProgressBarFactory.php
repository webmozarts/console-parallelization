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

namespace Webmozarts\Console\Parallelization\Logger;

use const PHP_FLOAT_MIN;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

// Since we cannot mock the ProgressBar, we have no choice but to use a simpler
// version of it instead to be able to use its output in a more predicable way
// than with the debug format.
final class TestProgressBarFactory implements ProgressBarFactory
{
    public function create(OutputInterface $output, int $numberOfItems): ProgressBar
    {
        $progressBar = new ProgressBar($output, $numberOfItems, PHP_FLOAT_MIN);
        // TODO: use the constant once we drop support for Symfony 4.4
        $progressBar->setFormat(/* ProgressBar::FORMAT_NORMAL */ 'normal');
        $progressBar->start();

        return $progressBar;
    }
}
