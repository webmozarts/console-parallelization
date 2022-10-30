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

trait ItemNamingCapabilities
{
    /**
     * @param positive-int|0|null $count
     */
    protected function getItemName(?int $count): string
    {
        if (null === $count) {
            return 'item(s)';
        }

        return $count > 1 ? 'items' : 'item';
    }
}
