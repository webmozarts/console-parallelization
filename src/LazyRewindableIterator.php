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

use Generator;
use Iterator;
use function array_key_exists;
use function count;

/**
 * The goal of this iterator is to make a non-rewindable iterable rewindable. This
 * is achieved by caching the values as the source iterator is iterated over.
 *
 * The values of the source iterator are not all loaded at once for being more performance
 * friendly.
 *
 * Note that as a side effect, the keys of the source iterator are not preserved.
 *
 * @internal
 *
 * @template TValue
 * @template-implements Iterator<int, TValue>
 */
final class LazyRewindableIterator implements Iterator
{
    /**
     * @var list<TValue>
     */
    private array $cache = [];

    /**
     * @var positive-int|0
     */
    private int $position = 0;

    private bool $sourceExhausted = false;

    /**
     * @param Generator<mixed, TValue> $source
     */
    private function __construct(private readonly Generator $source)
    {
    }

    /**
     * @param iterable<mixed, TValue> $source
     *
     * @return LazyRewindableIterator<TValue>
     */
    public static function create(iterable $source): self
    {
        return new self(
            self::createGenerator($source),
        );
    }

    /**
     * @param iterable<mixed, TValue> $iterable
     *
     * @return Generator<mixed, TValue>
     */
    private static function createGenerator(iterable $iterable): Generator
    {
        yield from $iterable;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @return TValue|null
     */
    public function current(): mixed
    {
        $this->fetchUntilPositionReached($this->position);

        return $this->cache[$this->position] ?? null;
    }

    public function key(): mixed
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function valid(): bool
    {
        $this->fetchUntilPositionReached($this->position);

        return array_key_exists($this->position, $this->cache);
    }

    private function fetchUntilPositionReached(int $index): void
    {
        if ($this->sourceExhausted) {
            return;
        }

        while (count($this->cache) <= $index) {
            if (!$this->source->valid()) {
                $this->sourceExhausted = true;
                break;
            }

            $this->cache[] = $this->source->current();
            $this->source->next();
        }
    }
}
