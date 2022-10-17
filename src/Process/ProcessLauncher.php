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

namespace Webmozarts\Console\Parallelization\Process;

use Iterator;

interface ProcessLauncher
{
    /**
     * Runs child processes to process the given items.
     *
     * @param list<string>|Iterator<string> $items The items to process. None of the items must
     *                                             contain newlines
     */
    public function run(iterable $items): void;
}
