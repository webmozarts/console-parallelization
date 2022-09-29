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

use function array_chunk;
use function array_filter;
use function array_values;
use Closure;
use function count;
use function explode;
use function get_class;
use function gettype;
use function is_numeric;
use function is_object;
use const PHP_EOL;
use function sprintf;
use function stream_get_contents;
use Webmozart\Assert\Assert;

final class ChunkedItemsIterator
{
    /**
     * @var list<string>
     */
    private array $items;

    /**
     * @var list<list<string>>
     */
    private array $itemsChunks;

    /**
     * @var 0|positive-int
     */
    private int $numberOfItems;

    /**
     * @param list<string> $items
     * @param positive-int $batchSize
     */
    public function __construct(array $items, int $batchSize)
    {
        $this->items = $items;
        $this->itemsChunks = array_chunk($this->items, $batchSize);
        $this->numberOfItems = count($this->items);
    }

    /**
     * @param resource$stream
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
     * @param callable():list<string> $fetchItems
     */
    public static function fromItemOrCallable(?string $item, Closure $fetchItems, int $batchSize): self
    {
        if (null !== $item) {
            $items = [$item];
        } else {
            $items = $fetchItems();

            Assert::isArray(
                $items,
                sprintf(
                    'Expected the fetched items to be a list of strings. Got "%s".',
                    // TODO: use get_debug_type when dropping support for PHP 7.4
                    gettype($items),
                ),
            );
        }

        return new self(
            self::normalizeItems($items),
            $batchSize,
        );
    }

    /**
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    /**
     * @return array<list<string>>
     */
    public function getItemChunks(): array
    {
        return $this->itemsChunks;
    }

    /**
     * @return 0|positive-int
     */
    public function getNumberOfItems(): int
    {
        return $this->numberOfItems;
    }

    /**
     * @psalm-assert string[] $items
     *
     * @return list<string>
     */
    private static function normalizeItems(array $items): array
    {
        foreach ($items as $index => $item) {
            if (is_numeric($item)) {
                $items[$index] = (string) $item;

                continue;
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
        }

        return array_values($items);
    }
}
