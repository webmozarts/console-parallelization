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
use Webmozart\Assert\Assert;
use function array_filter;
use function array_map;
use function array_merge;
use function Safe\getcwd;
use function sprintf;

/**
 * @internal
 */
final class ChildCommandFactory
{
    private string $phpExecutable;
    private string $scriptPath;
    private string $commandName;
    private InputDefinition $commandDefinition;

    public function __construct(
        string $phpExecutable,
        string $scriptPath,
        string $commandName,
        InputDefinition $commandDefinition
    ) {
        self::validateScriptPath($scriptPath);

        $this->phpExecutable = $phpExecutable;
        $this->scriptPath = $scriptPath;
        $this->commandName = $commandName;
        $this->commandDefinition = $commandDefinition;
    }

    /**
     * @return list<string>
     */
    public function createChildCommand(InputInterface $input): array
    {
        return array_merge(
            $this->createBaseCommand($input),
            $this->getForwardedOptions($input),
        );
    }

    /**
     * @return list<string>
     */
    private function createBaseCommand(
        InputInterface $input
    ): array {
        return array_filter([
            $this->phpExecutable,
            $this->scriptPath,
            $this->commandName,
            ...array_map('strval', self::getArguments($input)),
            '--child',
        ]);
    }

    /**
     * @return list<string>
     */
    private function getForwardedOptions(InputInterface $input): array
    {
        // Forward all the options except for "processes" to the children
        // this way the children can inherit the options such as env
        // or no-debug.
        return InputOptionsSerializer::serialize(
            $this->commandDefinition,
            $input,
            ParallelizationInput::OPTIONS,
        );
    }

    /**
     * @return list<string|bool|int|float|null|array<string|bool|int|float|null>>
     */
    private static function getArguments(InputInterface $input): array
    {
        $arguments = RawInput::getRawArguments($input);

        // Remove the item: we do not want it to be passed to child processes
        // ever.
        unset(
            $arguments['command'],
            $arguments[ParallelizationInput::ITEM_ARGUMENT],
        );

        return array_values($arguments);
    }

    private static function validateScriptPath(string $scriptPath): void
    {
        Assert::fileExists(
            $scriptPath,
            sprintf(
                'The script file could not be found at the path "%s" (working directory: %s)',
                $scriptPath,
                getcwd(),
            ),
        );
    }
}
