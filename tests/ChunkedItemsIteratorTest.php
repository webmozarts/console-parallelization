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
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
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
     * @dataProvider inputProvider
     *
     * @param Closure(): list<string> $fetchItems
     * @param list<string>            $expectedItems
     * @param array<list<string>>     $expectedItemChunks
     */
    public function test_it_can_be_created_from_an_input(
        ?string $item,
        Closure $fetchItems,
        int $batchSize,
        array $expectedItems,
        int $expectedNumberOfItems,
        array $expectedItemChunks
    ): void {
        $iterator = ChunkedItemsIterator::create($item, $fetchItems, $batchSize);

        self::assertStateIs(
            $iterator,
            $expectedItems,
            $expectedNumberOfItems,
            $expectedItemChunks,
        );
    }

    public function test_it_validates_the_items_provided_by_the_closure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the fetched items to be a list of strings. Got "object".');

        ChunkedItemsIterator::create(
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

        new ChunkedItemsIterator($items, $batchSize);
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

        yield 'numerical items – items are casted to strings' => [
            ['string item', 10, .5, 0x1A, 0b11111111],
            10,
            ['string item', '10', '0.5', '26', '255'],
            5,
            [
                ['string item', '10', '0.5', '26', '255'],
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

    public static function inputProvider(): iterable
    {
        yield 'one item: the fetch item closure is not evaluated' => [
            'item0',
            self::createFakeClosure(),
            1,
            ['item0'],
            1,
            [
                ['item0'],
            ],
        ];

        yield 'no item: the fetch item closure is evaluated' => [
            null,
            static function (): array {
                return ['item0', 'item1'];
            },
            2,
            ['item0', 'item1'],
            2,
            [
                ['item0', 'item1'],
            ],
        ];
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'invalid item type' => [
            [new stdClass()],
            1,
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "stdClass".',
        ];
    }

    private static function createFakeClosure(): Closure
    {
        return static function () {
            throw new LogicException('Did not expect to be called');
        };
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
