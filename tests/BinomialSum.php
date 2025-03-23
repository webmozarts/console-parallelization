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

// See https://math.pugetsound.edu/~mspivey/CombSum.pdf
// See https://mathworld.wolfram.com/BinomialSums.html
final class BinomialSum
{
    public const array A000079 = [
        1,
        2,
        4,
        8,
        16,
        32,
        64,
        128,
    ];
}
