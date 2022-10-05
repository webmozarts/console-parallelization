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

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;

/**
 * @covers \Webmozarts\Console\Parallelization\ParallelizationInput
 */
final class ParallelizationInputTest extends TestCase
{
    public function test_it_can_configure_a_command(): void
    {
        $command = new Command();

        $initialDefinition = $command->getDefinition();

        // Sanity check
        self::assertFalse($initialDefinition->hasArgument('item'));
        self::assertFalse($initialDefinition->hasOption('processes'));
        self::assertFalse($initialDefinition->hasOption('child'));

        ParallelizationInput::configureParallelization($command);

        $configuredDefinition = $command->getDefinition();

        self::assertTrue($configuredDefinition->hasArgument('item'));
        self::assertTrue($configuredDefinition->hasOption('processes'));
        self::assertTrue($configuredDefinition->hasOption('child'));
    }

    public function test_it_can_be_instantiated(): void
    {
        $input = new ParallelizationInput(
            true,
            5,
            'item',
            true,
        );

        self::assertTrue($input->isNumberOfProcessesDefined());
        self::assertSame(5, $input->getNumberOfProcesses());
        self::assertSame('item', $input->getItem());
        self::assertTrue($input->isChildProcess());
    }

    /**
     * @dataProvider inputProvider
     */
    public function test_it_can_be_instantiated_from_an_input(
        InputInterface $input,
        ParallelizationInput $expected
    ): void {
        self::bindInput($input);

        $actual = ParallelizationInput::fromInput($input);

        self::assertEquals($expected, $actual);
    }

    public function test_it_can_be_instantiated_from_an_input_with_an_invalid_item(): void
    {
        $input = new ArrayInput([
            'item' => new stdClass(),
        ]);
        self::bindInput($input);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid item type. Expected a string, got "object".');

        ParallelizationInput::fromInput($input);
    }

    public function test_it_can_be_instantiated_from_an_input_with_an_invalid_number_of_processes(): void
    {
        $input = new ArrayInput([
            '--processes' => 0,
        ]);
        self::bindInput($input);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected the number of processes to be 1 or greater. Got "0".');

        ParallelizationInput::fromInput($input);
    }

    /**
     * @dataProvider invalidNumberOfProcessesProvider
     */
    public function test_it_cannot_pass_an_invalid_number_of_processes(
        InputInterface $input,
        string $expectedErrorMessage
    ): void {
        self::bindInput($input);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        ParallelizationInput::fromInput($input);
    }

    public static function inputProvider(): iterable
    {
        yield 'empty input' => [
            new StringInput(''),
            new ParallelizationInput(
                false,
                1,
                null,
                false,
            ),
        ];

        yield 'number of process defined: 1' => [
            new StringInput('--processes=1'),
            new ParallelizationInput(
                true,
                1,
                null,
                false,
            ),
        ];

        yield 'number of process defined: 4' => [
            new StringInput('--processes=4'),
            new ParallelizationInput(
                true,
                4,
                null,
                false,
            ),
        ];

        yield 'item passed' => [
            new StringInput('item15'),
            new ParallelizationInput(
                false,
                1,
                'item15',
                false,
            ),
        ];

        yield 'integer item passed' => [
            new ArrayInput(['item' => 10]),
            new ParallelizationInput(
                false,
                1,
                '10',
                false,
            ),
        ];

        yield 'float item passed' => [
            new ArrayInput(['item' => -.5]),
            new ParallelizationInput(
                false,
                1,
                '-0.5',
                false,
            ),
        ];

        yield 'child option' => [
            new StringInput('--child'),
            new ParallelizationInput(
                false,
                1,
                null,
                true,
            ),
        ];

        yield 'nominal' => [
            new StringInput('item15 --child --processes 15'),
            new ParallelizationInput(
                true,
                15,
                'item15',
                true,
            ),
        ];
    }

    public static function invalidNumberOfProcessesProvider(): iterable
    {
        yield 'non numeric value' => [
            new StringInput('--processes foo'),
            'Expected the number of process defined to be a valid numeric value. Got "foo".',
        ];

        yield 'non integer value' => [
            new StringInput('--processes 1.5'),
            'Expected the number of process defined to be an integer. Got "1.5".',
        ];
    }

    private static function bindInput(InputInterface $input): void
    {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input->bind($command->getDefinition());
    }
}
