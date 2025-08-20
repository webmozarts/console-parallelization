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
use function iter\toArrayWithKeys;

/**
 * @internal
 */
#[CoversClass(LazyRewindableIterator::class)]
final class LazyRewindableIteratorTest extends TestCase
{
    #[DataProvider('iterableProvider')]
    public function test_iterates_over_all_values(iterable $input, array $expected): void
    {
        $iterator = LazyRewindableIterator::create($input);

        $actual = toArrayWithKeys($iterator);

        self::assertSame($expected, $actual);
    }

    #[DataProvider('iterableProvider')]
    public function test_is_automatically_rewindable(iterable $input, array $expected): void
    {
        $iterator = LazyRewindableIterator::create($input);

        $firstPass = toArrayWithKeys($iterator);
        $secondPass = toArrayWithKeys($iterator);

        self::assertSame($expected, $firstPass);
        self::assertSame($expected, $secondPass);
    }

    public static function iterableProvider(): iterable
    {
        yield 'array of strings' => [
            ['a', 'b', 'c'],
            ['a', 'b', 'c'],
        ];

        yield 'array with keys' => [
            ['a' => 1, 'b' => 2, 'c' => 3],
            [1, 2, 3],
        ];

        yield 'empty iterable' => [
            [],
            [],
        ];

        yield 'non-rewindable iterable: a generator' => [
            (static function () {
                yield 'a' => 10;
                yield 'b' => 20;
                yield 'c' => 30;
            })(),
            [10, 20, 30],
        ];

        yield 'non-rewindable iterable with overlapping keys' => [
            (static function () {
                yield 'a' => 10;
                yield 'b' => 20;
                yield 'c' => 30;
                yield 'c' => 40;
            })(),
            [10, 20, 30, 40],
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

        $iterator = LazyRewindableIterator::create($input);

        // Partially iterate
        $firstPass = [];
        foreach ($iterator as $i => $value) {
            $firstPass[] = $value;
            if (2 === $i) {
                break;
            }
        }

        $iterator->rewind();
        $secondPass = toArrayWithKeys($iterator);

        self::assertSame([1, 2, 3], $firstPass);
        self::assertSame([1, 2, 3, 4, 5], $secondPass);
    }

    public function test_can_be_partially_consumed_and_resumed(): void
    {
        $input = (static function () {
            yield 1;
            yield 2;
            yield 3;
            yield 4;
            yield 5;
        })();

        $iterator = LazyRewindableIterator::create($input);

        // Partially iterate
        $firstPass = [];
        foreach ($iterator as $i => $value) {
            $firstPass[] = $value;
            if (2 === $i) {
                break;
            }
        }

        $secondPass = toArrayWithKeys($iterator);

        self::assertSame([1, 2, 3], $firstPass);
        self::assertSame([1, 2, 3, 4, 5], $secondPass);
    }

    public function test_it_loads_values_lazily_and_do_not_loads_them_multiple_times(): void
    {
        $breakpoint1 = 0;
        $breakpoint2 = 0;
        $breakpoint3 = 0;

        $input = (static function () use (&$breakpoint1, &$breakpoint2, &$breakpoint3) {
            yield 1;
            yield 2;
            ++$breakpoint1;
            yield 3;
            yield 4;
            ++$breakpoint2;
            yield 5;
            ++$breakpoint3;
        })();

        $iterator = LazyRewindableIterator::create($input);

        // Partially iterate
        $firstPass = [];
        foreach ($iterator as $i => $value) {
            $firstPass[] = $value;
            if (2 === $i) {
                break;
            }
        }

        // Check that only the necessary values were fetched
        self::assertSame(1, $breakpoint1);
        self::assertSame(0, $breakpoint2);
        self::assertSame(0, $breakpoint3);

        $secondPass = toArrayWithKeys($iterator);

        self::assertSame([1, 2, 3], $firstPass);
        self::assertSame([1, 2, 3, 4, 5], $secondPass);

        // Check that the source iterator was fully consumed and the values not fetched more than once
        self::assertSame(1, $breakpoint1);
        self::assertSame(1, $breakpoint2);
        self::assertSame(1, $breakpoint3);
    }

    public function test_iterator_api_can_be_directly_consumed(): void
    {
        $input = (static function () {
            yield 'first';
            yield 'second';
            yield 'third';
        })();

        $iterator = LazyRewindableIterator::create($input);

        self::assertSame('first', $iterator->current());
    }
}
