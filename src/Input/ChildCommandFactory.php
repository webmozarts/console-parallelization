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
use function array_slice;
use function implode;
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
            // Forward all the options except for "processes" to the children
            // this way the children can inherit the options such as env
            // or no-debug.
            InputOptionsSerializer::serialize(
                $this->commandDefinition,
                $input,
                ['child', 'processes'],
            ),
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
            implode(
                ' ',
                // TODO: this looks suspicious: why do we need to take the first arg?
                //      why is this not a specific arg?
                //      why do we include optional arguments? (cf. options)
                //      maybe has to do with the item arg but in that case it is incorrect...
                array_filter(
                    array_slice(
                        array_map('strval', $input->getArguments()),
                        1,
                    ),
                ),
            ),
            '--child',
        ]);
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
