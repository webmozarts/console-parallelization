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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LazyRewindableIterator::class)]
final class LazyRewindableIteratorTest extends TestCase
{
    #[DataProvider('provideIterableCases')]
    public function test_iterates_all_values(iterable $input, array $expected): void
    {
        $iterator = new LazyRewindableIterator($input);
        $actual = iterator_to_array($iterator, preserve_keys: false);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('provideIterableCases')]
    public function test_is_rewindable(iterable $input, array $expected): void
    {
        $iterator = new LazyRewindableIterator($input);

        $firstPass = [];
        foreach ($iterator as $value) {
            $firstPass[] = $value;
        }

        $iterator->rewind();

        $secondPass = [];
        foreach ($iterator as $value) {
            $secondPass[] = $value;
        }

        self::assertSame($expected, $firstPass);
        self::assertSame($expected, $secondPass);
    }

    public static function provideIterableCases(): array
    {
        return [
            'array of ints' => [
                'input' => [1, 2, 3],
                'expected' => [1, 2, 3],
            ],
            'empty iterable' => [
                'input' => [],
                'expected' => [],
            ],
            'generator' => [
                'input' => (static function () {
                    yield 10;
                    yield 20;
                    yield 30;
                })(),
                'expected' => [10, 20, 30],
            ],
        ];
    }

    public function test_can_be_partially_consumed_and_rewound(): void
    {
        $input = (static function () {
            yield 1;
            yield 2;
            yield 3;
            yield 4;
            yield 5;
        })();

        $iterator = new LazyRewindableIterator($input);

        $firstPass = [];
        foreach ($iterator as $i => $value) {
            $firstPass[] = $value;
            if (2 === $i) {
                break;
            }
        }

        $iterator->rewind();

        $secondPass = iterator_to_array($iterator, preserve_keys: false);

        self::assertSame([1, 2, 3], $firstPass);
        self::assertSame([1, 2, 3, 4, 5], $secondPass);
    }

    public function test_handles_large_input_lazily(): void
    {
        $count = 100_000;
        $input = (static function () use ($count) {
            for ($i = 0; $i < $count; ++$i) {
                yield $i;
            }
        })();

        $iterator = new LazyRewindableIterator($input);

        $sliced = [];
        foreach ($iterator as $i => $val) {
            $sliced[] = $val;
            if ($i >= 10) {
                break;
            }
        }

        self::assertSame(range(0, 10), $sliced);

        $iterator->rewind();
        $again = iterator_to_array($iterator, preserve_keys: false);

        self::assertCount($count, $again);
        self::assertSame(range(0, $count - 1), $again);
    }
}
