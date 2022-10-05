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

namespace Webmozarts\Console\Parallelization;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozarts\Console\Parallelization\Fixtures\Command\NoSubProcessCommand;
use Webmozarts\Console\Parallelization\Integration\Kernel;

/**
 * @covers \Webmozarts\Console\Parallelization\InputOptionsSerializer
 */
final class InputOptionsSerializerTest extends TestCase
{
    /**
     * @dataProvider optionsProvider
     */
    public function test_it_can_reverse_the_options_parsing(
        InputDefinition $commandDefinition,
        InputInterface $input,
        array $excludedParameters,
        array $expected
    ): void {
        $input->bind($commandDefinition);

        $actual = InputOptionsSerializer::serialize(
            $commandDefinition,
            $input,
            $excludedParameters,
        );

        self::assertSame($expected, $actual);
    }

    public static function optionsProvider(): iterable
    {
        $completeInputDefinition = new InputDefinition([
            new InputArgument(
                'arg1',
                InputArgument::REQUIRED,
                'Argument without a default value',
            ),
            new InputArgument(
                'arg2',
                InputArgument::OPTIONAL,
                'Argument with a default value',
                'arg2DefaultValue',
            ),
            new InputOption(
                'opt1',
                null,
                InputOption::VALUE_REQUIRED,
                'Option with a default value',
                'opt1DefaultValue',
            ),
            new InputOption(
                'opt2',
                null,
                InputOption::VALUE_REQUIRED,
                'Option without a default value',
            ),
        ]);

        yield 'empty input and empty definition' => [
            new InputDefinition(),
            new ArrayInput([]),
            [],
            [],
        ];

        yield 'empty input and definition with default values' => [
            new InputDefinition([
                new InputArgument(
                    'arg1',
                    InputArgument::OPTIONAL,
                    'Argument with a default value',
                    'arg1DefaultValue',
                ),
                new InputOption(
                    'opt1',
                    null,
                    InputOption::VALUE_REQUIRED,
                    'Option with a default value',
                    'opt1DefaultValue',
                ),
            ]),
            new ArrayInput([]),
            [],
            [],
        ];

        yield 'input & options' => [
            $completeInputDefinition,
            new ArrayInput([
                'arg2' => 'arg2Value',
                '--opt2' => 'opt2Value',
            ]),
            [],
            ['--opt2=opt2Value'],
        ];

        yield 'input & options with default value' => [
            $completeInputDefinition,
            new ArrayInput([
                'arg2' => 'arg2Value',
                '--opt1' => 'opt1DefaultValue',
                '--opt2' => 'opt2Value',
            ]),
            [],
            [
                '--opt1=opt1DefaultValue',
                '--opt2=opt2Value',
            ],
        ];

        yield 'input & options with excluded option passed option' => [
            $completeInputDefinition,
            new ArrayInput([
                'arg2' => 'arg2Value',
                '--opt2' => 'opt2Value',
            ]),
            ['opt2'],
            [],
        ];

        yield 'input & options with excluded option default option' => [
            $completeInputDefinition,
            new ArrayInput([
                'arg2' => 'arg2Value',
                '--opt2' => 'opt2Value',
            ]),
            ['opt1'],
            ['--opt2=opt2Value'],
        ];

        yield 'nominal' => [
            self::createIntegrationInputDefinition(),
            new ArrayInput([
                'command' => 'test:no-subprocess',
                'item' => null,
                '--processes' => '1',
                '--env' => 'dev',
                '--ansi' => null,
            ]),
            ['child', 'processes'],
            [
                '--env=dev',
                '--ansi',
            ],
        ];

        yield from self::optionSerializationProvider();
    }

    private static function optionSerializationProvider(): iterable
    {
        $createSet = static fn (
            InputOption $option,
            array $input,
            array $expected
        ) => [
            new InputDefinition([$option]),
            new ArrayInput($input),
            [],
            $expected,
        ];

        yield 'option without value' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NONE,
            ),
            ['--opt' => null],
            ['--opt'],
        );

        yield 'option without value by shortcut' => $createSet(
            new InputOption(
                'opt',
                'o',
                InputOption::VALUE_NONE,
            ),
            ['-o' => null],
            ['--opt'],
        );

        yield 'option with value required' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 'foo'],
            ['--opt=foo'],
        );

        yield 'option with non string value (bool)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => true],
            ['--opt=1'],
        );

        yield 'option with non string value (int)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 20],
            ['--opt=20'],
        );

        yield 'option with non string value (float)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED,
            ),
            ['--opt' => 5.3],
            ['--opt=5.3'],
        );

        yield 'option with non string value (array of strings)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
            ),
            ['--opt' => ['v1', 'v2', 'v3']],
            ['--opt=v1--opt=v2--opt=v3'],
        );

        yield 'negatable option (positive)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NEGATABLE,
            ),
            ['--opt' => null],
            ['--opt'],
        );

        yield 'negatable option (negative)' => $createSet(
            new InputOption(
                'opt',
                null,
                InputOption::VALUE_NEGATABLE,
            ),
            ['--no-opt' => null],
            ['--no-opt'],
        );

        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[escape token] ',
            self::escapedValuesProvider(),
        );
    }

    private static function escapedValuesProvider(): iterable
    {
        $createSet = static fn (
            string $optionValue,
            ?string $expected
        ) => [
            new InputDefinition([
                new InputOption(
                    'opt',
                    null,
                    InputOption::VALUE_REQUIRED,
                ),
            ]),
            new ArrayInput([
                '--opt' => $optionValue,
            ]),
            [],
            [
                '--opt='.$expected,
            ],
        ];

        yield $createSet(
            'foo',
            'foo',
        );

        yield $createSet(
            '"foo"',
            '"\"foo\""',
        );

        yield $createSet(
            '"o_id in(\'20\')"',
            '"\"o_id in(\'20\')\""',
        );

        yield $createSet(
            'a b c d',
            '"a b c d"',
        );

        yield $createSet(
            "A\nB'C",
            "\"A\nB'C\"",
        );
    }

    private static function createIntegrationInputDefinition(): InputDefinition
    {
        // We need the framework bundle application because the env and no-debug
        // options are defined there.
        $frameworkBundleApplication = new Application(new Kernel());

        $command = new NoSubProcessCommand();
        $command->setApplication($frameworkBundleApplication);
        $command->mergeApplicationDefinition();

        return $command->getDefinition();
    }
}
