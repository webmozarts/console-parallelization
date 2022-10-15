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

namespace Webmozarts\Console\Parallelization\CI;

use stdClass;
use function count;

/**
 * @param list<non-empty-string> $packageNames
 * @param non-empty-string|null  $conflict
 */
function set_composer_conflicts(
    stdClass $decodedComposerJson,
    array $packageNames,
    ?string $conflict
): void {
    if (0 === count($packageNames)) {
        return;
    }

    $decodedComposerJson->conflict ??= new stdClass();

    if (null === $conflict) {
        foreach ($packageNames as $packageName) {
            unset($decodedComposerJson->conflict->{$packageName});
        }
    } else {
        foreach ($packageNames as $packageName) {
            $decodedComposerJson->conflict->{$packageName} = $conflict;
        }
    }
}
