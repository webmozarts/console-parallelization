<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\CI;

use stdClass;
use function count;

/**
 * @param list<non-empty-string> $packageNames
 * @param non-empty-string|null $conflict
 */
function set_composer_conflicts(
    stdClass $decodedComposerJson,
    array $packageNames,
    ?string $conflict
): void {
    if (count($packageNames) === 0) {
        return;
    }

    $decodedComposerJson->conflict = $decodedComposerJson->conflict ?? new stdClass();

    if (null === $conflict) {
        foreach ($packageNames as $packageName) {
            unset($decodedComposerJson->conflict->$packageName);
        }
    } else {
        foreach ($packageNames as $packageName) {
            $decodedComposerJson->conflict->$packageName = $conflict;
        }
    }
}
