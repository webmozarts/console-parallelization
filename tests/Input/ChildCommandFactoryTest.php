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
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use function Safe\getcwd;

/**
 * @covers \Webmozarts\Console\Parallelization\Input\ChildCommandFactory
 *
 * @internal
 */
final class ChildCommandFactoryTest extends TestCase
{
    /**
     * @dataProvider childProvider
     */
    public function test_it_can_launch_configured_child_processes(
        string $phpExecutable,
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
        $phpExecutable = __FILE__;
        $scriptPath = __DIR__.'/../../bin/console';
        $commandName = 'import:something';

        yield 'nominal' => (static function () use (
            $phpExecutable,
            $scriptPath,
            $commandName,
        ) {
            $input = new ArrayInput([
                'item' => 'item3',
                'groupId' => 'group2',
                '--child' => null,
                '--processes' => '2',
                '--opt' => 'val',
            ]);

            $commandDefinition = new InputDefinition([
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
            ]);
            $input->bind($commandDefinition);

            return [
                $phpExecutable,
                $scriptPath,
                $commandName,
                $commandDefinition,
                $input,
                [
                    $phpExecutable,
                    $scriptPath,
                    $commandName,
                    'group2',
                    '--child',
                    '--opt=val',
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
            __FILE__,
            'path/to/unknown',
            'import:something',
            new InputDefinition(),
        );
    }
}
