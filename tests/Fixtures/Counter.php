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

namespace Webmozarts\Console\Parallelization\Fixtures;

final class Counter
{
    private int $count = 0;

    public function increment(): void
    {
        ++$this->count;
    }

    public function getCount(): int
    {
        return $this->count;
    }
}
