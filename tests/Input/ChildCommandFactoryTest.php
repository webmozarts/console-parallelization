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

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Safe\getcwd;

/**
 * @internal
 */
#[CoversClass(ChildCommandFactory::class)]
final class ChildCommandFactoryTest extends TestCase
{
    #[DataProvider('childProvider')]
    public function test_it_can_launch_configured_child_processes(
        array $phpExecutable,
        string $scriptPath,
        string $commandName,
        InputDefinition $commandDefinition,
        InputInterface $input,
        array $expected
    ): void {
        $factory = new ChildCommandFactory(
            $phpExecutable,
            $scriptPath,
            $commandName,
            $commandDefinition,
        );

        $actual = $factory->createChildCommand($input);

        self::assertSame($expected, $actual);
    }

    public static function childProvider(): iterable
    {
        $phpExecutable = [__FILE__];
        $scriptPath = __DIR__.'/../../bin/console';
        $commandName = 'import:something';

        yield 'nominal' => (static function () use (
            $phpExecutable,
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [
                    'item' => 'item3',
                    'groupId' => 'group2',
                    '--child' => null,
                    '--processes' => '2',
                    '--opt' => 'val',
                ],
                [
                    new InputArgument(
                        'item',
                        InputArgument::REQUIRED,
                    ),
                    new InputArgument(
                        'groupId',
                        InputArgument::REQUIRED,
                    ),
                    new InputArgument(
                        'optArg',
                        InputArgument::OPTIONAL,
                        '',
                        '',
                    ),
                    new InputOption(
                        'opt',
                        null,
                        InputOption::VALUE_REQUIRED,
                    ),
                    new InputOption(
                        'child',
                        null,
                        InputOption::VALUE_NONE,
                    ),
                    new InputOption(
                        'processes',
                        null,
                        InputOption::VALUE_REQUIRED,
                    ),
                ],
            );

            return [
                $phpExecutable,
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    $phpExecutable[0],
                    $scriptPath,
                    $commandName,
                    'group2',
                    '--child',
                    '--opt=val',
                ],
            ];
        })();

        yield 'it removes the command & item argument' => (static function () use (
            $phpExecutable,
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [
                    'command' => 'import:something',
                    'groupId' => 'group2',
                    'categoryId' => 10,
                    'item' => 'item3',
                    'tagIds' => 'tag1 tag2 tag3',
                ],
                [
                    new InputArgument(
                        'groupId',
                        InputArgument::REQUIRED,
                    ),
                    new InputArgument(
                        'categoryId',
                        InputArgument::REQUIRED,
                    ),
                    // Inverse the order to break with the old code which was
                    // relying on item/command being the first argument.
                    new InputArgument(
                        'item',
                        InputArgument::REQUIRED,
                    ),
                    new InputArgument(
                        'command',
                        InputArgument::OPTIONAL,
                    ),
                    new InputArgument(
                        'tagIds',
                        InputArgument::IS_ARRAY,
                    ),
                ],
            );

            return [
                $phpExecutable,
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    $phpExecutable[0],
                    $scriptPath,
                    $commandName,
                    'group2',
                    '10',
                    'tag1 tag2 tag3',
                    '--child',
                ],
            ];
        })();

        yield 'it does not forward the parallel input' => (static function () use (
            $phpExecutable,
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [
                    '--processes' => '10',
                    '--main-process' => null,
                    '--child' => null,
                ],
                [
                    new InputOption(
                        ParallelizationInput::PROCESSES_OPTION,
                        null,
                        InputOption::VALUE_OPTIONAL,
                    ),
                    new InputOption(
                        ParallelizationInput::MAIN_PROCESS_OPTION,
                        null,
                        InputOption::VALUE_NONE,
                    ),
                    new InputOption(
                        ParallelizationInput::CHILD_OPTION,
                        null,
                        InputOption::VALUE_NONE,
                    ),
                ],
            );

            return [
                $phpExecutable,
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    $phpExecutable[0],
                    $scriptPath,
                    $commandName,
                    '--child',
                ],
            ];
        })();

        yield 'no PHP executable' => (static function () use (
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [],
                [],
            );

            return [
                [],
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    $scriptPath,
                    $commandName,
                    '--child',
                ],
            ];
        })();

        yield 'enriched PHP executable' => (static function () use (
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [],
                [],
            );

            return [
                ['/path/to/php', '-dmemory_limit=1'],
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    '/path/to/php',
                    '-dmemory_limit=1',
                    $scriptPath,
                    $commandName,
                    '--child',
                ],
            ];
        })();

        yield 'enriched PHP executable with space (array case)' => (static function () use (
            $scriptPath,
            $commandName
        ) {
            [$input, $commandDefinition] = self::createInput(
                [],
                [],
            );

            return [
                ['/path/to/my php', '-dmemory_limit=1'],
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    '/path/to/my php',
                    '-dmemory_limit=1',
                    $scriptPath,
                    $commandName,
                    '--child',
                ],
            ];
        })();

        yield 'no PHP executable or command' => (static function () use (
            $scriptPath
        ) {
            [$input, $commandDefinition] = self::createInput(
                [],
                [],
            );

            return [
                [],
                $scriptPath,
                '',
                $commandDefinition,
                $input,
                [
                    $scriptPath,
                    '--child',
                ],
            ];
        })();
    }

    public function test_it_cannot_create_a_factory_with_an_invalid_script_path(): void
    {
        $cwd = getcwd();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The script file could not be found at the path "path/to/unknown" (working directory: '.$cwd.')');

        new ChildCommandFactory(
            [__FILE__],
            'path/to/unknown',
            'import:something',
            new InputDefinition(),
        );
    }

    private static function createInput(
        array $input,
        array $definition
    ): array {
        $input = new ArrayInput($input);
        $inputDefinition = new InputDefinition($definition);

        $input->bind($inputDefinition);

        return [$input, $inputDefinition];
    }
}
