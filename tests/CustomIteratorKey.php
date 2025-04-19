<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use Stringable;

final readonly class CustomIteratorKey implements Stringable
{
    public function __construct(public string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}