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
use function get_class;
use function gettype;
use function is_array;
use function is_numeric;
use function is_object;
use function iter\chunk;
use function iter\mapWithKeys;
use function iter\values;
use function Safe\stream_get_contents;
use function sprintf;
use function str_replace;
use const PHP_EOL;

final class ChunkedItemsIterator
{
    /**
     * @var list<string>|Iterator<string>
     */
    private iterable $items;

    /**
     * @var Iterator<list<string>>
     */
    private Iterator $itemsChunks;

    /**
     * @var 0|positive-int|null
     */
    private ?int $numberOfItems;

    /**
     * @param list<string>|Iterator<string> $items
     * @param positive-int                  $batchSize
     */
    public function __construct(iterable $items, int $batchSize)
    {
        $this->items = $items;
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
     * @param mixed $items
     *
     * @return Iterator<string>
     */
    private static function normalizeItemStream($items): Iterator
    {
        Assert::isIterable(
            $items,
            sprintf(
                'Expected the fetched items to be a list or an iterable of strings. Got "%s".',
                // TODO: use get_debug_type when dropping PHP 7.4 support
                is_object($items) ? get_class($items) : gettype($items),
            ),
        );

        return values(
            mapWithKeys(
                static fn ($item, $index) => self::normalizeItem($item, $index),
                $items,
            ),
        );
    }

    /**
     * @param mixed     $item
     * @param array-key $index
     */
    private static function normalizeItem($item, $index): string
    {
        if (is_numeric($item)) {
            return (string) $item;
        }

        Assert::string(
            $item,
            sprintf(
                'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "%s" for the item "%s".',
                // TODO: use get_debug_type when dropping PHP 7.4 support
                is_object($item) ? get_class($item) : gettype($item),
                $index,
            ),
        );
        Assert::false(
            '' !== PHP_EOL && false !== mb_strpos($item, PHP_EOL),
            sprintf(
                'An item cannot contain a line return. Got one for "%s" for the item "%s".',
                str_replace(PHP_EOL, '<lineReturn>', $item),
                $index,
            ),
        );

        return $item;
    }
}
