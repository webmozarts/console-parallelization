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

namespace Webmozarts\Console\Parallelization;

final class PHPUnitProviderUtil
{
    private function __construct()
    {
    }

    /**
     * @template V
     * @param iterable<V> $provider
     *
     * @return iterable<string, V>
     */
    public static function prefixWithLabel(
        string $label,
        iterable $provider
    ): iterable {
        foreach ($provider as $key => $value) {
            yield $label.$key => $value;
        }
    }
}
