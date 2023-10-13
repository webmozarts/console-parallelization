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

use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

final class DebugProgressBarFactory implements ProgressBarFactory
{
    public function create(
        OutputInterface $output,
        int $numberOfItems
    ): ProgressBar {
        $progressBar = new ProgressBar($output, $numberOfItems);
        $progressBar->setFormat(ProgressBar::FORMAT_DEBUG);
        $progressBar->start();

        return $progressBar;
    }
}
