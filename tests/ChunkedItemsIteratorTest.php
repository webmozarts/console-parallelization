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

use InvalidArgumentException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SplObjectStorage;
use stdClass;
use function fclose;
use function iter\toArrayWithKeys;
use function iter\toIter;
use const PHP_EOL;

/**
 * @internal
 */
#[CoversClass(ChunkedItemsIterator::class)]
final class ChunkedItemsIteratorTest extends TestCase
{
    /**
     * @param list<string>       $expectedItems
     * @param list<list<string>> $expectedItemChunks
     */
    #[DataProvider('valuesProvider')]
    public function test_it_can_be_instantiated(
        iterable $items,
        int $batchSize,
        array $expectedItems,
        ?int $expectedNumberOfItems,
        array $expectedItemChunks
    ): void {
        $iterator = new ChunkedItemsIterator($items, $batchSize);

        self::assertStateIs(
            $iterator,
            $expectedItems,
            $expectedNumberOfItems,
            $expectedItemChunks,
        );
    }

    /**
     * @param resource     $stream
     * @param list<string> $expectedItems
     */
    #[DataProvider('streamProvider')]
    public function test_it_can_be_created_from_a_stream(
        $stream,
        array $expectedItems
    ): void {
        $iterator = ChunkedItemsIterator::fromStream($stream, 10);

        self::assertEquals($expectedItems, $iterator->getItems());

        @fclose($stream);
    }

    /**
     * @param callable():list<string> $fetchItems
     * @param list<string>            $expectedItems
     */
    #[DataProvider('inputProvider')]
    public function test_it_can_be_created_from_an_an_item_or_a_callable(
        ?string $item,
        callable $fetchItems,
        array $expectedItems
    ): void {
        $iterator = ChunkedItemsIterator::fromItemOrCallable($item, $fetchItems, 10);

        self::assertSame($expectedItems, toArrayWithKeys($iterator->getItems()));
    }

    public function test_it_validates_the_items_provided_by_the_closure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the fetched items to be a list or an iterable of strings. Got "stdClass".');

        ChunkedItemsIterator::fromItemOrCallable(
            null,
            static fn () => new stdClass(),
            1,
        );
    }

    public function test_it_lazily_validates_the_items_provided_by_the_closure_when_it_is_not_an_array(): void
    {
        $iterator = ChunkedItemsIterator::fromItemOrCallable(
            null,
            static function () {
                yield new stdClass();
            },
            1,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "0".');

        // Trigger the loading
        toArrayWithKeys($iterator->getItems());
    }

    public function test_it_lazily_evaluates_non_array_iterables(): void
    {
        $itemsFetched = false;

        $iterator = ChunkedItemsIterator::fromItemOrCallable(
            null,
            static function () use (&$itemsFetched) {
                $itemsFetched = true;

                yield 'item1';
                yield 'item2';
            },
            10,
        );

        // Sanity check
        self::assertFalse($itemsFetched);

        self::assertNull($iterator->getNumberOfItems());
        // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertFalse($itemsFetched);

        self::assertSame(['item1', 'item2'], toArrayWithKeys($iterator->getItems()));
        self::assertTrue($itemsFetched);
    }

    #[DataProvider('invalidValuesProvider')]
    public function test_it_cannot_be_instantiated_with_invalid_data(
        ?string $item,
        iterable $items,
        int $batchSize,
        string $expectedErrorMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        $iterator = ChunkedItemsIterator::fromItemOrCallable(
            $item,
            static fn () => $items,
            $batchSize,
        );

        // In case of the iterator, since it is lazily evaluated it will not
        // be validated when the ChunkedItemsIterator is instantiated.
        toArrayWithKeys($iterator->getItems());
    }

    public static function valuesProvider(): iterable
    {
        yield 'nominal' => [
            ['item0', 'item1', 'item3', 'item4'],
            2,
            ['item0', 'item1', 'item3', 'item4'],
            4,
            [
                ['item0', 'item1'],
                ['item3', 'item4'],
            ],
        ];

        yield 'less items than batch size' => [
            ['item0', 'item1', 'item3'],
            4,
            ['item0', 'item1', 'item3'],
            3,
            [
                ['item0', 'item1', 'item3'],
            ],
        ];

        yield 'same number of items as batch size' => [
            ['item0', 'item1', 'item3'],
            3,
            ['item0', 'item1', 'item3'],
            3,
            [
                ['item0', 'item1', 'item3'],
            ],
        ];

        yield 'more items than batch size' => [
            ['item0', 'item1', 'item3'],
            2,
            ['item0', 'item1', 'item3'],
            3,
            [
                ['item0', 'item1'],
                ['item3'],
            ],
        ];

        yield 'unknown number of items' => [
            toIter(['item0', 'item1', 'item3', 'item4']),
            2,
            ['item0', 'item1', 'item3', 'item4'],
            null,
            [
                ['item0', 'item1'],
                ['item3', 'item4'],
            ],
        ];

        yield 'non-rewindable generator' => [
            (static function () {
                yield 'item0';
                yield 'item1';
                yield 'item2';
                yield 'item3';
            })(),
            2,
            ['item0', 'item1', 'item2', 'item3'],
            null,
            [
                ['item0', 'item1'],
                ['item2', 'item3'],
            ],
        ];

        yield 'iterator with a stringeable key' => [
            (static function () {
                yield new CustomIteratorKey('a') => 'item0';
                yield new CustomIteratorKey('b') => 'item1';
                yield new CustomIteratorKey('c') => 'item2';
                yield new CustomIteratorKey('d') => 'item3';
            })(),
            2,
            ['item0', 'item1', 'item2', 'item3'],
            null,
            [
                ['item0', 'item1'],
                ['item2', 'item3'],
            ],
        ];

        yield 'iterator with a non-stringeable key' => [
            (static function () {
                yield (object) ['α' => 'alpha'] => 'item0';
                yield (object) ['β' => 'beta'] => 'item1';
                yield (object) ['γ' => 'gamma'] => 'item2';
                yield (object) ['δ' => 'detla'] => 'item3';
            })(),
            2,
            ['item0', 'item1', 'item2', 'item3'],
            null,
            [
                ['item0', 'item1'],
                ['item2', 'item3'],
            ],
        ];
    }

    public static function streamProvider(): iterable
    {
        yield 'single item' => [
            StringStream::fromString('item0'),
            ['item0'],
        ];

        yield 'single item with space' => [
            StringStream::fromString('it em'),
            ['it em'],
        ];

        yield 'empty string' => [
            StringStream::fromString(''),
            [],
        ];

        yield 'whitespace string' => [
            StringStream::fromString(' '),
            [' '],
        ];

        yield 'several items' => [
            StringStream::fromString(
                <<<'STDIN'
                    item0
                    item1
                    item3
                    STDIN,
            ),
            ['item0', 'item1', 'item3'],
        ];

        yield 'several items with blank values' => [
            StringStream::fromString(
                <<<'STDIN'
                    item0
                    item1

                    item3

                    item4
                    STDIN,
            ),
            ['item0', 'item1', 'item3', 'item4'],
        ];

        yield 'numerical items – items are kept as strings' => [
            StringStream::fromString(
                <<<'STDIN'
                    string item
                    10
                    .5
                    0x1A
                    0b11111111
                    STDIN,
            ),
            ['string item', '10', '.5', '0x1A', '0b11111111'],
        ];
    }

    public static function inputProvider(): iterable
    {
        yield 'one item: the fetch item closure is not evaluated' => [
            'item0',
            FakeCallable::create(),
            ['item0'],
        ];

        yield 'no item: the fetch item closure is evaluated' => [
            null,
            static fn () => ['item0', 'item1'],
            ['item0', 'item1'],
        ];

        yield 'indexed items' => [
            null,
            static fn () => ['i0' => 'item0', 'i1' => 'item1'],
            ['item0', 'item1'],
        ];

        yield 'item closure; non string string values' => [
            null,
            static fn () => [0, -.5, 7.3, 'item1'],
            ['0', '-0.5', '7.3', 'item1'],
        ];

        yield 'item closure with iterator; non string string values' => [
            null,
            static fn () => toIter([0, -.5, 7.3, 'item1']),
            ['0', '-0.5', '7.3', 'item1'],
        ];

        yield 'item closure with indexed iterator; non string string values' => [
            null,
            static fn () => toIter(['i0' => 'item0', 'i1' => 'item1']),
            ['item0', 'item1'],
        ];

        yield 'iterator with a stringeable key' => [
            null,
            static function () {
                yield new CustomIteratorKey('a') => 'item0';
                yield new CustomIteratorKey('b') => 'item1';
                yield new CustomIteratorKey('c') => 'item2';
                yield new CustomIteratorKey('d') => 'item3';
            },
            ['item0', 'item1', 'item2', 'item3'],
        ];

        yield 'iterator with a non-stringeable key' => [
            null,
            static function () {
                yield (object) ['α' => 'alpha'] => 'item0';
                yield (object) ['β' => 'beta'] => 'item1';
                yield (object) ['γ' => 'gamma'] => 'item2';
                yield (object) ['δ' => 'detla'] => 'item3';
            },
            ['item0', 'item1', 'item2', 'item3'],
        ];
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'stdClass item' => [
            null,
            [new stdClass()],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "0".',
        ];

        yield 'closure item' => [
            null,
            [FakeCallable::create()],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "Closure" for the item "0".',
        ];

        yield 'boolean item' => [
            null,
            [true],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "bool" for the item "0".',
        ];

        yield 'single item with line return' => [
            'it'.PHP_EOL.'em',
            [],
            1,
            'An item cannot contain a line return. Got one for "it<lineReturn>em" for the item "0".',
        ];

        yield 'an item with a line return' => [
            null,
            ['item0', 'it'.PHP_EOL.'em', 'item1'],
            1,
            'An item cannot contain a line return. Got one for "it<lineReturn>em" for the item "1".',
        ];

        yield 'an item with a line return (iterable)' => [
            null,
            toIter(['item0', 'it'.PHP_EOL.'em', 'item1']),
            1,
            'An item cannot contain a line return. Got one for "it<lineReturn>em" for the item "1".',
        ];

        yield 'iterable with unorthodox keys' => [
            null,
            (static function () {
                $storage = new SplObjectStorage();
                $storage[new stdClass()] = 'it'.PHP_EOL.'em';

                return $storage;
            })(),
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "0".',
        ];

        yield 'iterator with a stringeable key' => [
            null,
            (static function () {
                yield new CustomIteratorKey('a') => new stdClass();
            })(),
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "a".',
        ];

        yield 'iterator with a non-stringeable key' => [
            null,
            (static function () {
                yield (object) ['a' => 'alpha'] => new stdClass();
            })(),
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "<NonStringableKey>".',
        ];
    }

    private static function assertStateIs(
        ChunkedItemsIterator $iterator,
        array $expectedItems,
        ?int $expectedNumberOfItems,
        array $expectedItemChunks
    ): void {
        self::assertSame($expectedItems, toArrayWithKeys($iterator->getItems()));
        self::assertSame($expectedNumberOfItems, $iterator->getNumberOfItems());
        self::assertSame($expectedItemChunks, toArrayWithKeys($iterator->getItemChunks()));
    }
}
