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

interface ProgressBarFactory
{
    /**
     * @param 0|positive-int $numberOfItems
     */
    public function create(
        OutputInterface $output,
        int $numberOfItems
    ): ProgressBar;
}
