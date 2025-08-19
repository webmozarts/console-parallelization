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

use Symfony\Component\Process\PhpExecutableFinder as SymfonyPhpExecutableFinder;
use Webmozart\Assert\Assert;

final class PhpExecutableFinder
{
    private static SymfonyPhpExecutableFinder $finder;

    private function __construct()
    {
    }

    /**
     * TODO: Remove this in 3.x. This is purely for the BC layer.
     * @internal
     */
    public static function tryToFind(): ?string
    {
        $phpExecutable = self::getFinder()->find();

        return false === $phpExecutable ? null : $phpExecutable;
    }

    /**
     * @return non-empty-list<string>
     */
    public static function find(): array
    {
        $finder = self::getFinder();
        $phpExecutable = $finder->find(false);

        Assert::notFalse(
            $phpExecutable,
            'Could not find the PHP executable.',
        );

        return [
            $phpExecutable,
            ...$finder->findArguments(),
        ];
    }

    private static function getFinder(): SymfonyPhpExecutableFinder
    {
        if (!isset(self::$finder)) {
            self::$finder = new SymfonyPhpExecutableFinder();
        }

        return self::$finder;
    }
}
