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

use Closure;
use function fclose;
use function fopen;
use function fwrite;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use function rewind;
use stdClass;

/**
 * @covers \Webmozarts\Console\Parallelization\ChunkedItemsIterator
 */
final class ChunkedItemsIteratorTest extends TestCase
{
    /**
     * @dataProvider valuesProvider
     *
     * @param list<string>        $expectedItems
     * @param array<list<string>> $expectedItemChunks
     */
    public function test_it_can_be_instantiated(
        array $items,
        int $batchSize,
        array $expectedItems,
        int $expectedNumberOfItems,
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
     * @dataProvider streamProvider
     *
     * @param resource     $stream
     * @param list<string> $expectedItems
     */
    public function test_it_can_be_created_from_a_stream(
        $stream,
        array $expectedItems
    ): void {
        $iterator = ChunkedItemsIterator::fromStream($stream, 10);

        self::assertEquals($expectedItems, $iterator->getItems());

        @fclose($stream);
    }

    /**
     * @dataProvider inputProvider
     *
     * @param callable():list<string> $fetchItems
     * @param list<string>            $expectedItems
     */
    public function test_it_can_be_created_from_an_an_item_or_a_callable(
        ?string $item,
        callable $fetchItems,
        array $expectedItems
    ): void {
        $iterator = ChunkedItemsIterator::fromItemOrCallable($item, $fetchItems, 10);

        self::assertEquals($expectedItems, $iterator->getItems());
    }

    public function test_it_validates_the_items_provided_by_the_closure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the fetched items to be a list of strings. Got "object".');

        ChunkedItemsIterator::fromItemOrCallable(
            null,
            static function () {
                yield from [];
            },
            1,
        );
    }

    /**
     * @dataProvider invalidValuesProvider
     */
    public function test_it_cannot_be_instantiated_with_invalid_data(
        array $items,
        int $batchSize,
        string $expectedErrorMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        ChunkedItemsIterator::fromItemOrCallable(
            null,
            static fn () => $items,
            $batchSize,
        );
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
    }

    public static function streamProvider(): iterable
    {
        yield 'single item' => [
            self::createStream('item0'),
            ['item0'],
        ];

        yield 'single item with space' => [
            self::createStream('it em'),
            ['it em'],
        ];

        yield 'empty string' => [
            self::createStream(''),
            [],
        ];

        yield 'whitespace string' => [
            self::createStream(' '),
            [' '],
        ];

        yield 'several items' => [
            self::createStream(<<<'STDIN'
                item0
                item1
                item3
                STDIN),
            ['item0', 'item1', 'item3'],
        ];

        yield 'several items with blank values' => [
            self::createStream(<<<'STDIN'
                item0
                item1

                item3

                item4
                STDIN),
            ['item0', 'item1', 'item3', 'item4'],
        ];

        yield 'numerical items – items are kept as strings' => [
            self::createStream(<<<'STDIN'
                string item
                10
                .5
                0x1A
                0b11111111
                STDIN),
            ['string item', '10', '.5', '0x1A', '0b11111111'],
        ];
    }

    public static function inputProvider(): iterable
    {
        yield 'one item: the fetch item closure is not evaluated' => [
            'item0',
            self::createFakeClosure(),
            ['item0'],
        ];

        yield 'no item: the fetch item closure is evaluated' => [
            null,
            static fn () => ['item0', 'item1'],
            ['item0', 'item1'],
        ];
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'stdClass item' => [
            [new stdClass()],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass" for the item "0".',
        ];

        yield 'closure item' => [
            [self::createFakeClosure()],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "Closure" for the item "0".',
        ];

        yield 'boolean item' => [
            [true],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "boolean" for the item "0".',
        ];
    }

    private static function createFakeClosure(): Closure
    {
        return static function () {
            throw new LogicException('Did not expect to be called');
        };
    }

    /**
     * @return resource
     */
    private static function createStream(string $value)
    {
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $value);
        rewind($stream);

        return $stream;
    }

    private static function assertStateIs(
        ChunkedItemsIterator $iterator,
        array $expectedItems,
        int $expectedNumberOfItems,
        array $expectedItemChunks
    ): void {
        self::assertSame($expectedItems, $iterator->getItems());
        self::assertSame($expectedNumberOfItems, $iterator->getNumberOfItems());
        self::assertSame($expectedItemChunks, $iterator->getItemChunks());
    }
}
