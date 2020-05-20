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

use Closure;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use function func_get_args;

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
        $this->assertFalse($initialDefinition->hasArgument('item'));
        $this->assertFalse($initialDefinition->hasOption('processes'));
        $this->assertFalse($initialDefinition->hasOption('child'));

        ParallelizationInput::configureParallelization($command);

        $configuredDefinition = $command->getDefinition();

        $this->assertTrue($configuredDefinition->hasArgument('item'));
        $this->assertTrue($configuredDefinition->hasOption('processes'));
        $this->assertTrue($configuredDefinition->hasOption('child'));
    }

    /**
     * @dataProvider inputProvider
     *
     * @param Closure(InputInterface): string[] $itemsFetcher
     * @param string[]                          $expectedItems
     */
    public function test_it_can_be_instantiated(
        InputInterface $input,
        Closure $itemsFetcher,
        int $segmentSize,
        int $batchSize,
        bool $expectedIsNumberOfProcessesDefined,
        int $expectedNumberOfProcesses,
        array $expectedItems,
        int $expectedItemsCount,
        int $expectedSegmentSize,
        int $expectedBatchSize,
        int $expectedRounds,
        int $expectedBatches
    ): void {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input->bind($command->getDefinition());

        $parallelizationInput = new ParallelizationInput(
            $input,
            $itemsFetcher,
            $segmentSize,
            $batchSize
        );

        $this->assertSame(
            $expectedIsNumberOfProcessesDefined,
            $parallelizationInput->isNumberOfProcessesDefined()
        );
        $this->assertSame($expectedNumberOfProcesses, $parallelizationInput->getNumberOfProcesses());
        $this->assertSame($expectedItems, $parallelizationInput->getItems());
        $this->assertSame($expectedItemsCount, $parallelizationInput->getItemsCount());
        $this->assertSame($expectedSegmentSize, $parallelizationInput->getSegmentSize());
        $this->assertSame($expectedBatchSize, $parallelizationInput->getBatchSize());
        $this->assertSame($expectedRounds, $parallelizationInput->getRounds());
        $this->assertSame($expectedBatches, $parallelizationInput->getBatches());
    }

    /**
     * @dataProvider invalidNumberOfProcessesProvider
     */
    public function test_it_cannot_pass_an_invalid_number_of_processes(
        InputInterface $input,
        string $expectedErrorMessage
    ): void {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input->bind($command->getDefinition());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        new ParallelizationInput(
            $input,
            self::createFakeClosure(),
            1,
            1
        );
    }

    /**
     * @dataProvider invalidItemsFetcherProvider
     */
    public function test_it_expects_the_items_fetcher_to_return_serialized_items(
        Closure $itemsFetcher,
        string $expectedErrorMessage
    ): void {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input = new StringInput('');
        $input->bind($command->getDefinition());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        new ParallelizationInput(
            $input,
            $itemsFetcher,
            1,
            1
        );
    }

    /**
     * @dataProvider itemsFetcherProvider
     *
     * @param list<string> $expectedItems
     */
    public function test_it_normalizes_the_fetched_items(
        Closure $itemsFetcher,
        array $expectedItems
    ): void {
        $command = new Command();

        ParallelizationInput::configureParallelization($command);

        $input = new StringInput('');
        $input->bind($command->getDefinition());

        $parallelizationInput = new ParallelizationInput(
            $input,
            $itemsFetcher,
            1,
            1
        );

        $this->assertSame($expectedItems, $parallelizationInput->getItems());
    }

    public static function inputProvider(): iterable
    {
        yield 'empty input' => self::createInputArgs(
            new StringInput(''),
            static function (): array {
                return ['item0', 'item1'];
            },
            10,
            5,
            false,
            1,
            ['item0', 'item1'],
            2,
            2,
            5,
            1,
            2
        );

        yield 'number of process defined: 1' => self::createInputArgs(
            new StringInput('--processes=1'),
            static function (): array {
                return ['item0', 'item1'];
            },
            10,
            5,
            true,
            1,
            ['item0', 'item1'],
            2,
            10,
            5,
            1,
            2
        );

        yield 'number of process defined: 4' => self::createInputArgs(
            new StringInput('--processes=4'),
            static function (): array {
                return ['item0', 'item1'];
            },
            10,
            5,
            true,
            4,
            ['item0', 'item1'],
            2,
            10,
            5,
            1,
            2
        );

        yield 'one item passed – items fetched not called' => self::createInputArgs(
            new StringInput('item15'),
            self::createFakeClosure(),
            10,
            5,
            false,
            1,
            ['item15'],
            1,
            1,
            5,
            1,
            2
        );

        yield 'empty input with string items' => self::createInputArgs(
            new StringInput(''),
            static function (): array {
                return ['item0', 'item1'];
            },
            10,
            5,
            false,
            1,
            ['item0', 'item1'],
            2,
            2,
            5,
            1,
            2
        );

        yield 'empty input with integer items – items are "serialized"' => self::createInputArgs(
            new StringInput(''),
            static function (): array {
                return [1000, 1001];
            },
            10,
            5,
            false,
            1,
            ['1000', '1001'],
            2,
            2,
            5,
            1,
            2
        );

        yield 'segment size with no process defined: takes the item count' => self::createInputArgs(
            new StringInput(''),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3'];
            },
            1,
            5,
            false,
            1,
            ['item0', 'item1', 'item2', 'item3'],
            4,
            4,
            5,
            1,
            1
        );

        yield 'segment size with no process defined: takes the given segment size' => self::createInputArgs(
            new StringInput('--processes=7'),
            static function (): array {
                return ['item0', 'item1'];
            },
            7,
            5,
            true,
            7,
            ['item0', 'item1'],
            2,
            7,
            5,
            1,
            2
        );

        yield 'number of rounds: 1 process = 1 round' => self::createInputArgs(
            new StringInput(''),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3'];
            },
            1,
            5,
            false,
            1,
            ['item0', 'item1', 'item2', 'item3'],
            4,
            4,
            5,
            1,
            1
        );

        yield 'number of rounds: 2 process just enough for the number of items' => self::createInputArgs(
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3'];
            },
            2,
            1,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3'],
            4,
            2,
            1,
            2,
            4
        );

        yield 'number of rounds: 2 process - half' => self::createInputArgs(
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4'];
            },
            2,
            1,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4'],
            5,
            2,
            1,
            3,
            6
        );

        yield 'number of rounds: 2 process - under' => [
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4'];
            },
            4,
            1,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4'],
            5,
            4,
            1,
            2,
            8,
        ];

        yield 'number of rounds: 2 process - up' => [
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4', 'item5', 'item6', 'item7'];
            },
            4,
            1,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4', 'item5', 'item6', 'item7'],
            8,
            4,
            1,
            2,
            8,
        ];

        yield 'number of batches: 2 process - half' => self::createInputArgs(
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4'];
            },
            3,
            2,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4'],
            5,
            3,
            2,
            2,
            4
        );

        yield 'number of batches: 2 process - under' => self::createInputArgs(
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4'];
            },
            5,
            4,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4'],
            5,
            5,
            4,
            1,
            2
        );

        yield 'number of batches: 2 process - up' => self::createInputArgs(
            new StringInput('--processes=2'),
            static function (): array {
                return ['item0', 'item1', 'item2', 'item3', 'item4'];
            },
            7,
            3,
            true,
            2,
            ['item0', 'item1', 'item2', 'item3', 'item4'],
            5,
            7,
            3,
            1,
            3
        );
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

        yield 'non >=1 value' => [
            new StringInput('--processes 0'),
            'Requires at least one process. Got "0"',
        ];
    }

    public static function invalidItemsFetcherProvider(): iterable
    {
        yield 'non array' => [
            static function () {
                return new stdClass();
            },
            'Expected the fetched items to be a list of strings. Got "object"',
        ];

        yield 'array with object item' => [
            static function () {
                return [new stdClass()];
            },
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "object" for the item "0"',
        ];

        yield 'array with object item with index' => [
            static function () {
                return ['foo' => new stdClass()];
            },
            'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "object" for the item "foo"',
        ];
    }

    public static function itemsFetcherProvider(): iterable
    {
        yield 'empty array' => [
            static function () {
                return [];
            },
            [],
        ];

        yield 'string values' => [
            static function () {
                return ['item0', 'item1'];
            },
            ['item0', 'item1'],
        ];

        yield 'string values with keys' => [
            static function () {
                return ['foo' => 'item0', 'bar' => 'item1'];
            },
            ['item0', 'item1'],
        ];

        yield 'numeric values' => [
            static function () {
                return [1.5, 5];
            },
            ['1.5', '5'],
        ];

        yield 'numeric values with keys' => [
            static function () {
                return ['foo' => 1.5, 'bar' => 5];
            },
            ['1.5', '5'],
        ];
    }

    private static function createFakeClosure(): Closure
    {
        return static function () {
            throw new LogicException('Did not expect to be called');
        };
    }

    private static function createInputArgs(
        InputInterface $input,
        Closure $itemsFetcher,
        int $segmentSize,
        int $batchSize,
        bool $expectedIsNumberOfProcessesDefined,
        int $expectedNumberOfProcesses,
        array $expectedItems,
        int $expectedItemsCount,
        int $expectedSegmentSize,
        int $expectedBatchSize,
        int $expectedRounds,
        int $expectedBatches
    ): array {
        return func_get_args();
    }
}
