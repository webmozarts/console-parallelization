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
use function method_exists;
use function preg_match;
use function sprintf;
use function str_replace;

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
            RawOptionsInput::getRawOptions($input),
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
        $value
    ): string {
        // TODO: remove the method exists check once we drop support for Symfony 4.4
        if (method_exists(InputOption::class, 'isNegatable') && $option->isNegatable()) {
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

    /**
     * @param string|bool|int|float|null $value
     */
    private static function serializeOptionWithValue(
        string $name,
        $value
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
     *
     * @param string|bool|int|float|null $value
     *
     * @return string|bool|int|float|null
     */
    private static function quoteOptionValue($value)
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
     * @param mixed $value
     */
    private static function isValueRequiresQuoting($value): bool
    {
        return is_string($value) && 0 < preg_match(self::ESCAPE_TOKEN_PATTERN, $value);
    }
}
