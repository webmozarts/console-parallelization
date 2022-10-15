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

require_once __DIR__.'/../bin/set-composer-conflicts.php';

use PHPUnit\Framework\TestCase;
use stdClass;
use function is_array;
use function Webmozarts\Console\Parallelization\CI\set_composer_conflicts;

/**
 * @coversNothing
 *
 * @internal
 */
final class SetComposerConflictsTest extends TestCase
{
    /**
     * @dataProvider conflictProvider
     *
     * @param list<non-empty-string> $packageNames
     * @param non-empty-string|null  $conflict
     */
    public function test_it_can_the_desired_packages_to_the_composer_conflict_section(
        stdClass $decodedComposerJson,
        array $packageNames,
        ?string $conflict,
        stdClass $expected
    ): void {
        set_composer_conflicts($decodedComposerJson, $packageNames, $conflict);

        self::assertEquals($expected, $decodedComposerJson);
    }

    public static function conflictProvider(): iterable
    {
        yield 'nominal' => [
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
            ]),
            ['symfony/cache', 'symfony/framework-bundle'],
            '>=5.0',
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
                'conflict' => [
                    'symfony/cache' => '>=5.0',
                    'symfony/framework-bundle' => '>=5.0',
                ],
            ]),
        ];

        yield 'no packages' => [
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
            ]),
            [],
            '>=5.0',
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
            ]),
        ];

        yield 'already has a conflict section' => [
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
                'conflict' => [
                    'symfony/service-contracts' => '<2.0',
                ],
            ]),
            ['symfony/cache', 'symfony/framework-bundle'],
            '>=5.0',
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
                'conflict' => [
                    'symfony/service-contracts' => '<2.0',
                    'symfony/cache' => '>=5.0',
                    'symfony/framework-bundle' => '>=5.0',
                ],
            ]),
        ];

        yield 'removes conflicts if no conflict is provided' => [
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
                'conflict' => [
                    'symfony/service-contracts' => '<2.0',
                    'symfony/cache' => '>=5.0',
                    'symfony/framework-bundle' => '>=5.0',
                ],
            ]),
            ['symfony/cache', 'symfony/framework-bundle'],
            null,
            self::toStdClass([
                'package' => 'webmozarts/console-parallelization',
                'conflict' => [
                    'symfony/service-contracts' => '<2.0',
                ],
            ]),
        ];
    }

    private static function toStdClass(array $value): stdClass
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::toStdClass($item);
            }
        }

        return (object) $value;
    }
}
