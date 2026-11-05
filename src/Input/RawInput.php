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
use function method_exists;

/**
 * @internal
 *
 * TODO: remove this class once the minimum symfony/console version is 8.1 and
 *       call Input::getRawArguments() and Input::getRawOptions() directly.
 */
final class RawInput extends Input
{
    /**
     * @codeCoverageIgnore
     */
    private function __construct(?InputDefinition $definition)
    {
        parent::__construct($definition);
    }

    /**
     * Returns all the given arguments NOT merged with the default values.
     *
     * @return array<string|bool|int|float|null|array<string|bool|int|float|null>>
     */
    public static function extractRawArguments(InputInterface $input): array
    {
        if (!$input instanceof Input) {
            return [];
        }

        return method_exists($input, 'getRawArguments')
            ? $input->getRawArguments()
            : $input->arguments;
    }

    /**
     * Returns all the given options NOT merged with the default values.
     *
     * @return array<string|bool|int|float|null|array<string|bool|int|float|null>>
     */
    public static function extractRawOptions(InputInterface $input): array
    {
        if (!$input instanceof Input) {
            return [];
        }

        return method_exists($input, 'getRawOptions')
            ? $input->getRawOptions()
            : $input->options;
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
     *
     * @param mixed[]|string $values
     */
    public function hasParameterOption(array|string $values, bool $onlyParams = false): bool
    {
        throw new DomainException('Not implemented.');
    }

    /**
     * @codeCoverageIgnore
     *
     * @param mixed[]|string                     $values
     * @param null|array|bool|mixed[]|int|string $default
     */
    public function getParameterOption(array|string $values, array|bool|float|int|string|null $default = false, bool $onlyParams = false): mixed
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

    public function __toString(): string
    {
        return 'RawInput';
    }
}
