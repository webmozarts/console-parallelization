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
use function explode;
use function Safe\getcwd;
use function sprintf;

/**
 * @internal
 */
final readonly class ChildCommandFactory
{
    public function __construct(
        private string $phpExecutable,
        private string $scriptPath,
        private string $commandName,
        private InputDefinition $commandDefinition,
    ) {
        self::validateScriptPath($scriptPath);
    }

    /**
     * @return list<string>
     */
    public function createChildCommand(InputInterface $input): array
    {
        return [...$this->createBaseCommand($input), ...$this->getForwardedOptions($input)];
    }

    /**
     * @return array<int, string>
     */
    private function createBaseCommand(
        InputInterface $input
    ): array {
        return array_filter([
            ...$this->getEscapedPhpExecutable(),
            $this->scriptPath,
            $this->commandName,
            ...array_map(strval(...), self::getArguments($input)),
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
     * @return list<string>
     */
    private function getEscapedPhpExecutable(): array
    {
        return explode(' ', $this->phpExecutable);
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
