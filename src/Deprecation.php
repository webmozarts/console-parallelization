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

use function trigger_deprecation;

/**
 * @internal
 */
final class Deprecation
{
    /**
     * @param string $message The message of the deprecation
     * @param mixed  ...$args Values to insert in the message using printf() formatting
     */
    public static function trigger(string $message, ...$args): void
    {
        trigger_deprecation(
            'webmozarts/console-parallelization',
            '2.0.0',
            $message,
            $args,
        );
    }

    private function __construct()
    {
    }
}
