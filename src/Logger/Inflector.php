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

use Webmozart\Assert\Assert;

/**
 * @internal
 */
final class Inflector
{
    private const array PLURAL_MAP = [
        'batch' => 'batches',
        'round' => 'rounds',
        'child process' => 'parallel child processes',
    ];

    /**
     * @param positive-int|0 $count
     */
    public static function pluralize(string $singular, int $count): string
    {
        Assert::keyExists(
            self::PLURAL_MAP,
            $singular,
            'Expected the singular word to be a known word from the inflector dictionary. Got %s.',
        );

        return 0 === $count || 1 === $count ? $singular : self::PLURAL_MAP[$singular];
    }

    private function __construct()
    {
    }
}
