<?php

/*
 * This file is part of the Fidry\Console package.
 *
 * (c) Théo FIDRY <theo.fidry@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
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

    /**
     * @dataProvider inputProvider
     */
    public function test_it_can_be_instantiated(
        InputInterface $input,
        bool $expectedIsNumberOfProcessesDefined,
        int $expectedNumberOfProcesses,
        ?string $expectedItem,
        bool $expectedIsChildProcess
    ): void {
        self::bindInput($input);

        $parallelizationInput = new ParallelizationInput($input);

        self::assertSame(
            $expectedIsNumberOfProcessesDefined,
            $parallelizationInput->isNumberOfProcessesDefined()
        );
        self::assertSame($expectedNumberOfProcesses, $parallelizationInput->getNumberOfProcesses());
        self::assertSame($expectedItem, $parallelizationInput->getItem());
        self::assertSame($expectedIsChildProcess, $parallelizationInput->isChildProcess());
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

        new ParallelizationInput($input);
    }

    public static function inputProvider(): iterable
    {
        yield 'empty input' => [
            new StringInput(''),
            false,
            1,
            null,
            false,
        ];

        yield 'number of process defined: 1' => [
            new StringInput('--processes=1'),
            true,
            1,
            null,
            false,
        ];

        yield 'number of process defined: 4' => [
            new StringInput('--processes=4'),
            true,
            4,
            null,
            false,
        ];

        yield 'item passed' => [
            new StringInput('item15'),
            false,
            1,
            'item15',
            false,
        ];

        yield 'integer item passed' => [
            new ArrayInput(['item' => 10]),
            false,
            1,
            '10',
            false,
        ];

        yield 'float item passed' => [
            new ArrayInput(['item' => -.5]),
            false,
            1,
            '-0.5',
            false,
        ];

        yield 'child option' => [
            new StringInput('--child'),
            false,
            1,
            null,
            true,
        ];

        yield 'nominal' => [
            new StringInput('item15 --child --processes 15'),
            true,
            15,
            'item15',
            true,
        ];
    }

    public static function invalidNumberOfProcessesProvider(): iterable
    {
        yield 'non numeric value' => [
            new StringInput('--processes foo'),
            'Expected the number of process defined to be a valid numeric value. Got "foo"',
        ];

        yield 'non integer value' => [
            new StringInput('--processes 1.5'),
            'Expected the number of process defined to be an integer. Got "1.5"',
        ];
    }

    private static function bindInput(InputInterface $input): void
    {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input->bind($command->getDefinition());
    }
}
