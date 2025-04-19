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

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function array_diff_key;
use function array_fill_keys;
use function array_keys;
use function array_map;
use function array_merge;
use function sprintf;

/**
 * @internal
 */
final class InputOptionsSerializer
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $excludedOptionNames
     *
     * @return list<string>
     */
    public static function serialize(
        InputDefinition $commandDefinition,
        InputInterface $input,
        array $excludedOptionNames,
    ): array {
        $filteredOptions = array_diff_key(
            RawInput::getRawOptions($input),
            array_fill_keys($excludedOptionNames, ''),
        );

        $serializedOptionsList = [];

        foreach (array_keys($filteredOptions) as $name) {
            $serializedOptionsList[] = self::serializeOption(
                $commandDefinition->getOption($name),
                $name,
                $filteredOptions[$name],
            );
        }

        return array_merge(...$serializedOptionsList);
    }

    /**
     * @param string|bool|int|float|null|array<string|bool|int|float|null> $value
     *
     * @return list<string>
     */
    private static function serializeOption(
        InputOption $option,
        string $name,
        array|bool|float|int|string|null $value,
    ): array {
        // @phpstan-ignore-next-line return.type argument.type
        return match (true) {
            $option->isNegatable() => [sprintf('--%s%s', $value ? '' : 'no-', $name)],
            !$option->acceptValue() => [sprintf('--%s', $name)],
            /** @var list<string|bool|int|float|null> $value */
            // @phpstan-ignore-next-line argument.type
            $option->isArray() => array_map(static fn ($item) => self::serializeOptionWithValue($name, $item), $value),
            /** @var string|int|float|null $value */
            // @phpstan-ignore-next-line argument.type
            default => [self::serializeOptionWithValue($name, $value)],
        };
    }

    private static function serializeOptionWithValue(
        string $name,
        bool|float|int|string|null $value,
    ): string {
        return sprintf('--%s=%s', $name, $value);
    }
}
