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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozarts\Console\Parallelization\SymfonyVersion;

/**
 * @covers \Webmozarts\Console\Parallelization\Input\RawOptionsInput
 */
final class RawOptionsInputTest extends TestCase
{
    /**
     * @dataProvider inputOptionProvider
     */
    public function test_it_can_get_an_input_options(
        InputInterface $input,
        array $expected
    ): void {
        $actual = RawOptionsInput::getRawOptions($input);

        self::assertSame($expected, $actual);
    }

    public static function inputOptionProvider(): iterable
    {
        $isSymfony4 = SymfonyVersion::isSymfony4();

        yield 'input with no options' => [
            new ArrayInput([], null),
            [],
        ];

        yield 'input with options default options' => [
            new ArrayInput(
                [],
                new InputDefinition([
                    new InputOption(
                        'opt1',
                        null,
                        InputOption::VALUE_OPTIONAL,
                    ),
                    new InputOption(
                        'opt2',
                        null,
                        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                    ),
                    new InputOption(
                        'opt3',
                        null,
                        InputOption::VALUE_REQUIRED,
                    ),
                    new InputOption(
                        'opt5',
                        null,
                        InputOption::VALUE_NONE,
                    ),
                ]),
            ),
            [],
        ];

        if (!$isSymfony4) {
            // TODO: move this within the test up once we drop support for Symfony 4.4
            yield 'input with negatable options default options' => [
                new ArrayInput(
                    [],
                    new InputDefinition([
                        new InputOption(
                            'opt4',
                            null,
                            InputOption::VALUE_NEGATABLE,
                        ),
                    ]),
                ),
                [],
            ];
        }

        yield 'input with options' => [
            new ArrayInput(
                [
                    '--opt1' => 'value1',
                    '--opt3' => 'value3',
                    '--opt5' => null,
                ],
                new InputDefinition([
                    new InputOption(
                        'opt1',
                        null,
                        InputOption::VALUE_OPTIONAL,
                    ),
                    new InputOption(
                        'opt2',
                        null,
                        InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                    ),
                    new InputOption(
                        'opt3',
                        null,
                        InputOption::VALUE_REQUIRED,
                    ),
                    new InputOption(
                        'opt5',
                        null,
                        InputOption::VALUE_NONE,
                    ),
                ]),
            ),
            [
                'opt1' => 'value1',
                'opt3' => 'value3',
                'opt5' => true,
            ],
        ];

        if (!$isSymfony4) {
            // TODO: move this within the test up once we drop support for Symfony 4.4
            yield 'input with negatable options' => [
                new ArrayInput(
                    [
                        '--opt1' => 'value1',
                        '--opt3' => 'value3',
                        '--opt5' => null,
                    ],
                    new InputDefinition([
                        new InputOption(
                            'opt4',
                            null,
                            InputOption::VALUE_NEGATABLE,
                        ),
                    ]),
                ),
                [],
            ];
        }

        yield 'non standard input' => [
            new FakeInput(),
            [],
        ];
    }
}
