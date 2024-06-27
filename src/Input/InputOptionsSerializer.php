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
use function implode;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;

/**
 * @internal
 */
final class InputOptionsSerializer
{
    private const ESCAPE_TOKEN_PATTERN = '/[\s\W]/';

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
        array $excludedOptionNames
    ): array {
        $filteredOptions = array_diff_key(
            RawInput::getRawOptions($input),
            array_fill_keys($excludedOptionNames, ''),
        );

        return array_map(
            static fn (string $name) => self::serializeOption(
                $commandDefinition->getOption($name),
                $name,
                $filteredOptions[$name],
            ),
            array_keys($filteredOptions),
        );
    }

    /**
     * @param string|bool|int|float|null|array<string|bool|int|float|null> $value
     */
    private static function serializeOption(
        InputOption $option,
        string $name,
        array|bool|float|int|string|null $value,
    ): string {
        if ($option->isNegatable()) {
            return sprintf(
                '--%s%s',
                $value ? '' : 'no-',
                $name,
            );
        }

        if (!$option->acceptValue()) {
            return sprintf(
                '--%s',
                $name,
            );
        }

        if ($option->isArray()) {
            /** @var array<string|bool|int|float|null> $value */
            return implode(
                '',
                array_map(
                    static fn ($item) => self::serializeOptionWithValue($name, $item),
                    $value,
                ),
            );
        }

        /** @var string|bool|int|float|null $value */
        return self::serializeOptionWithValue($name, $value);
    }

    private static function serializeOptionWithValue(
        string $name,
        bool|float|int|string|null $value
    ): string {
        return sprintf(
            '--%s=%s',
            $name,
            self::quoteOptionValue($value),
        );
    }

    /**
     * Ensure that an option value is quoted correctly before it is passed to a
     * child process.
     */
    private static function quoteOptionValue(bool|float|int|string|null $value): bool|float|int|string|null
    {
        if (self::isValueRequiresQuoting($value)) {
            return sprintf(
                '"%s"',
                str_replace('"', '\"', (string) $value),
            );
        }

        return $value;
    }

    /**
     * Validate whether a command option requires quoting.
     */
    private static function isValueRequiresQuoting(mixed $value): bool
    {
        return is_string($value) && 0 < preg_match(self::ESCAPE_TOKEN_PATTERN, $value);
    }
}
