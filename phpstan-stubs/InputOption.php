<?php

declare(strict_types=1);

namespace Symfony\Component\Console\Input;

class InputOption
{
    /**
     * Returns true if the option can take multiple values.
     *
     * @return bool true if mode is self::VALUE_IS_ARRAY, false otherwise
     */
    public function isArray(): bool;
}