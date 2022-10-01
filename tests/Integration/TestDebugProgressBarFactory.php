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

use const PHP_FLOAT_MIN;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\Logger\ProgressBarFactory;

final class TestDebugProgressBarFactory implements ProgressBarFactory
{
    public function create(
        OutputInterface $output,
        int $numberOfItems
    ): ProgressBar {
        // Put the lowest time between redraws to ensure they we see all elements
        // of progress.
        $progressBar = new ProgressBar($output, $numberOfItems, PHP_FLOAT_MIN);
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);
        $progressBar->start();

        return $progressBar;
    }
}
