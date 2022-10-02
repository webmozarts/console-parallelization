<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use DomainException;

final class FakeCallable
{
    private function __construct()
    {
    }

    public static function create(): callable
    {
        return static function(): void {
            throw new DomainException('Unexpected call.');
        };
    }
}
