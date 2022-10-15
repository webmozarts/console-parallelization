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

namespace Webmozarts\Console\Parallelization\Input;

use DomainException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
final class RawOptionsInput extends Input
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct(?InputDefinition $definition)
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

    /**
     * @codeCoverageIgnore
     */
    public function getFirstArgument(): ?string
    {
        throw new DomainException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     * @param mixed $values
     * @param mixed $onlyParams
     */
    public function hasParameterOption($values, $onlyParams = false): bool
    {
        throw new DomainException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     * @param mixed $values
     * @param mixed $default
     * @param mixed $onlyParams
     */
    public function getParameterOption($values, $default = false, $onlyParams = false): void
    {
        throw new DomainException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     */
    protected function parse(): void
    {
        throw new DomainException('Not implemented.');
    }
}
