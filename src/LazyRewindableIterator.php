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
 * @internal
 */
final class LazyRewindableIterator implements Iterator
{
    private iterable $source;
    private array $cache = [];
    private int $position = 0;
    private bool $sourceExhausted = false;
    private ?Generator $generator = null;

    public function __construct(iterable $source)
    {
        $this->source = $source;
        $this->generator = $this->getGenerator($source);
    }

    private function getGenerator(iterable $iterable): Generator
    {
        yield from $iterable;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function current(): mixed
    {
        $this->fetchUntil($this->position);

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
        $this->fetchUntil($this->position);

        return array_key_exists($this->position, $this->cache);
    }

    private function fetchUntil(int $index): void
    {
        if ($this->sourceExhausted) {
            return;
        }

        while (count($this->cache) <= $index) {
            if (!$this->generator->valid()) {
                $this->sourceExhausted = true;
                break;
            }

            $this->cache[] = $this->generator->current();
            $this->generator->next();
        }
    }
}
