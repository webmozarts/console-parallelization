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

use Iterator;
use Webmozart\Assert\Assert;
use function array_filter;
use function array_keys;
use function array_map;
use function count;
use function explode;
use function is_array;
use function is_numeric;
use function iter\chunk;
use function iter\mapWithKeys;
use function iter\values;
use function Safe\stream_get_contents;
use function sprintf;
use function str_contains;
use function str_replace;
use const PHP_EOL;

final readonly class ChunkedItemsIterator
{
    /**
     * @var Iterator<list<string>>
     */
    private Iterator $itemsChunks;

    /**
     * @var 0|positive-int|null
     */
    private ?int $numberOfItems;

    /**
     * @internal Use the static factory methods instead.
     *
     * @param list<string>|Iterator<string> $items
     * @param positive-int                  $batchSize
     */
    public function __construct(
        private iterable $items,
        int $batchSize,
    ) {
        $this->itemsChunks = chunk($items, $batchSize);
        $this->numberOfItems = is_array($items) ? count($items) : null;
    }

    /**
     * @param resource     $stream
     * @param positive-int $batchSize
     */
    public static function fromStream($stream, int $batchSize): self
    {
        return new self(
            self::normalizeItems(
                array_filter(
                    explode(
                        PHP_EOL,
                        stream_get_contents($stream),
                    ),
                ),
            ),
            $batchSize,
        );
    }

    /**
     * @param callable():iterable<string> $fetchItems
     * @param positive-int                $batchSize
     */
    public static function fromItemOrCallable(?string $item, callable $fetchItems, int $batchSize): self
    {
        if (null !== $item) {
            $validatedItems = [self::normalizeItem($item, 0)];
        } else {
            $items = $fetchItems();

            $validatedItems = is_array($items)
                ? self::normalizeItems($items)
                : self::normalizeItemStream($items);
        }

        return new self($validatedItems, $batchSize);
    }

    /**
     * @return list<string>|Iterator<string>
     */
    public function getItems(): iterable
    {
        return $this->items;
    }

    /**
     * @return Iterator<list<string>>
     */
    public function getItemChunks(): Iterator
    {
        return $this->itemsChunks;
    }

    /**
     * @return 0|positive-int|null
     */
    public function getNumberOfItems(): ?int
    {
        return $this->numberOfItems;
    }

    /**
     * @param mixed[] $items
     *
     * @return list<string>
     */
    private static function normalizeItems(array $items): array
    {
        return array_map(
            static fn ($index) => self::normalizeItem($items[$index], $index),
            array_keys($items),
        );
    }

    /**
     * @return Iterator<string>
     */
    private static function normalizeItemStream(mixed $items): Iterator
    {
        Assert::isIterable(
            $items,
            sprintf(
                'Expected the fetched items to be a list or an iterable of strings. Got "%s".',
                get_debug_type($items),
            ),
        );

        return values(
            mapWithKeys(
                static fn ($item, $index) => self::normalizeItem($item, $index),
                $items,
            ),
        );
    }

    private static function normalizeItem(mixed $item, mixed $index): string
    {
        if (is_numeric($item)) {
            return (string) $item;
        }

        Assert::string(
            $item,
            sprintf(
                'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "%s" for the item "%s".',
                get_debug_type($item),
                $index,
            ),
        );
        Assert::false(
            '' !== PHP_EOL && str_contains($item, PHP_EOL),
            sprintf(
                'An item cannot contain a line return. Got one for "%s" for the item "%s".',
                str_replace(PHP_EOL, '<lineReturn>', $item),
                $index,
            ),
        );

        return $item;
    }
}
