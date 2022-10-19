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
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Webmozarts\Console\Parallelization\CpuCoreCounter;

/**
 * @covers \Webmozarts\Console\Parallelization\Input\ParallelizationInput
 *
 * @internal
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
        self::assertFalse($initialDefinition->hasOption('main-process'));
        self::assertFalse($initialDefinition->hasOption('child'));

        ParallelizationInput::configureCommand($command);

        $configuredDefinition = $command->getDefinition();

        self::assertTrue($configuredDefinition->hasArgument('item'));
        self::assertTrue($configuredDefinition->hasOption('processes'));
        self::assertTrue($initialDefinition->hasOption('main-process'));
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

        self::assertTrue($input->shouldBeProcessedInMainProcess());
        self::assertSame(5, $input->getNumberOfProcesses());
        self::assertSame('item', $input->getItem());
        self::assertTrue($input->isChildProcess());
    }

    public function test_it_can_be_instantiated_with_a_lazily_evaluated_closure_for_the_number_of_processes(): void
    {
        $closureEvaluated = false;

        $input = new ParallelizationInput(
            true,
            static function () use (&$closureEvaluated): int {
                $closureEvaluated = true;

                return 5;
            },
            'item',
            true,
        );

        self::assertTrue($input->shouldBeProcessedInMainProcess());
        self::assertSame('item', $input->getItem());
        self::assertTrue($input->isChildProcess());

        self::assertFalse($closureEvaluated);
        self::assertSame(5, $input->getNumberOfProcesses());
        self::assertTrue($closureEvaluated);
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

    public function test_it_can_be_instantiated_from_an_input_with_an_item_for_a_child_process(): void
    {
        $input = new ArrayInput([
            'item' => 'item1',
            '--child' => null,
        ]);
        self::bindInput($input);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot have an item passed to a child process as an argument. Got "item1"');

        ParallelizationInput::fromInput($input);
    }

    public function test_it_cannot_be_instantiated_from_an_input_with_an_invalid_number_of_processes(): void
    {
        $input = new ArrayInput([
            '--processes' => '0',
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
        $findNumberOfProcesses = static fn () => CpuCoreCounter::getNumberOfCpuCores();

        yield 'empty input' => [
            new StringInput(''),
            new ParallelizationInput(
                false,
                $findNumberOfProcesses,
                null,
                false,
            ),
        ];

        yield 'number of process defined: 1' => [
            new StringInput('--processes=1'),
            new ParallelizationInput(
                false,
                1,
                null,
                false,
            ),
        ];

        yield 'number of process defined: 4' => [
            new StringInput('--processes=4'),
            new ParallelizationInput(
                false,
                4,
                null,
                false,
            ),
        ];

        yield 'item passed' => [
            new StringInput('item15'),
            new ParallelizationInput(
                false,
                $findNumberOfProcesses,
                'item15',
                false,
            ),
        ];

        yield 'integer item passed' => [
            new ArrayInput(['item' => 10]),
            new ParallelizationInput(
                false,
                $findNumberOfProcesses,
                '10',
                false,
            ),
        ];

        yield 'float item passed' => [
            new ArrayInput(['item' => -.5]),
            new ParallelizationInput(
                false,
                $findNumberOfProcesses,
                '-0.5',
                false,
            ),
        ];

        yield 'child option' => [
            new StringInput('--child'),
            new ParallelizationInput(
                false,
                $findNumberOfProcesses,
                null,
                true,
            ),
        ];

        yield 'do processing in the main process' => [
            new StringInput('--main-process --processes 15'),
            new ParallelizationInput(
                true,
                1,
                null,
                false,
            ),
        ];

        yield 'do processing in the main process without number of processes specified' => [
            new StringInput('--main-process'),
            new ParallelizationInput(
                true,
                1,
                null,
                false,
            ),
        ];

        yield 'nominal' => [
            new StringInput('--child --processes 15'),
            new ParallelizationInput(
                false,
                15,
                null,
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

        ParallelizationInput::configureCommand($command);

        $input->bind($command->getDefinition());
    }
}
