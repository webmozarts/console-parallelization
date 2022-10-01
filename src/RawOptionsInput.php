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

use DomainException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class RawOptionsInput extends Input
{
    private function __construct(?InputDefinition $definition = null)
    {
        parent::__construct($definition);
    }

    /**
     * Returns all the given options NOT merged with the default values.
     *
     * @return array<string|bool|int|float|null|array<string|bool|int|float|null>>
     */
    public static function getRawOptions(InputInterface $input): array
    {
        return $input instanceof Input
            ? $input->options
            : [];
    }

    public function getFirstArgument()
    {
        throw new DomainException('Not implemented.');
    }

    public function hasParameterOption($values, bool $onlyParams = false)
    {
        throw new DomainException('Not implemented.');
    }

    public function getParameterOption($values, $default = false, bool $onlyParams = false)
    {
        throw new DomainException('Not implemented.');
    }

    protected function parse()
    {
        throw new DomainException('Not implemented.');
    }
}
