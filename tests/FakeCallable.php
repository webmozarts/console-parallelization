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

final class FakeCallable
{
    private function __construct()
    {
    }

    public static function create(): callable
    {
        return static function (): never {
            throw UnexpectedCall::forMethod(__METHOD__);
        };
    }
}
