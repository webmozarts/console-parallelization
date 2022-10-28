<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use function array_key_exists;
use function array_keys;
use function array_map;
use function Safe\putenv;

final class EnvironmentVariables
{
    /**
     * @param array<string, string> $environmentVariables
     *
     * @return callable():void Cleanup method: restores the previous state.
     */
    public static function setVariables(array $environmentVariables): callable
    {
        $restoreEnvironmentVariables = array_map(
            static fn (string $name) => self::setVariable($name, $environmentVariables[$name]),
            array_keys($environmentVariables),
        );

        return static function () use ($restoreEnvironmentVariables): void {
            foreach ($restoreEnvironmentVariables as $restoreEnvironmentVariable) {
                $restoreEnvironmentVariable();
            }
        };
    }

    /**
     * @return callable():void
     */
    private static function setVariable(string $name, string $value): callable
    {
        if (array_key_exists($name, $_SERVER)) {
            $previousValue = $_SERVER[$name];

            $restoreServer = static fn () => $_SERVER[$name] = $previousValue;
        } else {
            $restoreServer = static function () use ($name): void {
                unset($_SERVER[$name]);
            };
        }

        if (array_key_exists($name, $_ENV)) {
            $previousValue = $_ENV[$name];

            $restoreEnv = static fn () => $_SERVER[$name] = $previousValue;
        } else {
            $restoreEnv = static function () use ($name): void {
                unset($_ENV[$name]);
            };
        }

        putenv($name.'='.$value);
        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;

        return static function () use ($restoreServer, $restoreEnv, $name): void {
            putenv($name.'=');
            $restoreServer();
            $restoreEnv();
        };
    }

    private function __construct()
    {
    }
}
