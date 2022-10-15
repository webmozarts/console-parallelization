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

use function Safe\fopen;
use function fwrite;
use function rewind;

final class StringStream
{
    private function __construct()
    {
    }

    /**
     * @return resource
     */
    public static function fromString(string $value)
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $value);
        rewind($stream);

        return $stream;
    }
}
