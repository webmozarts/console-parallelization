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

use function func_get_args;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Webmozarts\Console\Parallelization\Configuration
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @dataProvider valuesProvider
     */
    public function test_it_can_be_instantiated(
        bool $numberOfProcessesDefined,
        int $numberOfProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        int $expectedSegmentSize,
        int $expectedNumberOfSegments,
        int $expectedNumberOfBatches
    ): void {
        $config = new Configuration(
            $numberOfProcessesDefined,
            $numberOfProcesses,
            $numberOfItems,
            $segmentSize,
            $batchSize
        );

        self::assertSame($expectedSegmentSize, $config->getSegmentSize());
        self::assertSame($expectedNumberOfSegments, $config->getNumberOfSegments());
        self::assertSame($expectedNumberOfBatches, $config->getNumberOfBatches());
    }

    /**
     * @dataProvider invalidValuesProvider
     */
    public function test_it_cannot_be_instantiated_with_invalid_values(
        int $numberOfProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        string $expectedErrorMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        new Configuration(
            true,
            $numberOfProcesses,
            $numberOfItems,
            $segmentSize,
            $batchSize
        );
    }

    public static function valuesProvider(): iterable
    {
        yield 'empty' => self::createInputArgs(
            false,
            1,
            0,
            1,
            1,
            0,
            1,
            1
        );

        yield 'only one default process: the segment size is the number of items' => self::createInputArgs(
            false,
            1,
            50,
            1,
            1,
            50,
            1,
            1
        );

        yield 'an arbitrary number of processes given: the segment size is the segment size given' => self::createInputArgs(
            true,
            7,
            50,
            3,
            1,
            3,
            17,
            51
        );

        yield 'one process given: the segment size is the segment size given' => self::createInputArgs(
            true,
            1,
            50,
            3,
            1,
            3,
            1,
            3
        );

        // Invalid domain case but we add this test to capture this behaviour nonetheless
        yield 'multiple default processes: the segment size is the segment size given' => self::createInputArgs(
            true,
            7,
            50,
            3,
            1,
            3,
            17,
            51
        );

        yield 'there is no rounds if there is no items' => self::createInputArgs(
            false,
            1,
            0,
            1,
            1,
            0,
            1,
            1
        );

        yield 'there is only one round if only one process (default)' => self::createInputArgs(
            false,
            1,
            50,
            1,
            1,
            50,
            1,
            1
        );

        yield 'there is only one round if only one process (arbitrary)' => self::createInputArgs(
            true,
            1,
            50,
            1,
            1,
            1,
            1,
            1
        );

        yield 'there is enough rounds to reach the number of items with the given segment size (half)' => self::createInputArgs(
            true,
            2,
            50,
            25,
            1,
            25,
            2,
            50
        );

        yield 'there is enough rounds to reach the number of items with the given segment size (upper)' => self::createInputArgs(
            true,
            2,
            50,
            15,
            1,
            15,
            4,
            60
        );

        yield 'there is enough rounds to reach the number of items with the given segment size (lower)' => self::createInputArgs(
            true,
            2,
            50,
            40,
            1,
            40,
            2,
            80
        );

        yield 'the batch size used is the batch size given' => self::createInputArgs(
            false,
            1,
            0,
            10,
            7,
            0,
            1,
            2
        );

        yield 'there is enough batches to process all the items of a given segment (half)' => self::createInputArgs(
            true,
            2,
            50,
            30,
            15,
            30,
            2,
            4
        );

        yield 'there is enough batches to process all the items of a given segment (upper)' => self::createInputArgs(
            true,
            2,
            50,
            30,
            10,
            30,
            2,
            6
        );

        yield 'there is enough batches to process all the items of a given segment (lower)' => self::createInputArgs(
            true,
            2,
            50,
            30,
            25,
            30,
            2,
            4
        );
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'invalid number of processes (limit)' => [
            0,
            0,
            1,
            1,
            'Expected the number of processes to be 1 or greater. Got "0"',
        ];

        yield 'invalid number of processes' => [
            -1,
            0,
            1,
            1,
            'Expected the number of processes to be 1 or greater. Got "-1"',
        ];

        yield 'invalid number of items (limit)' => [
            1,
            -1,
            1,
            1,
            'Expected the number of items to be 0 or greater. Got "-1"',
        ];

        yield 'invalid number of items' => [
            1,
            -10,
            1,
            1,
            'Expected the number of items to be 0 or greater. Got "-10"',
        ];

        yield 'invalid segment size (limit)' => [
            1,
            0,
            0,
            1,
            'Expected the segment size to be 1 or greater. Got "0"',
        ];

        yield 'invalid segment size' => [
            1,
            0,
            -1,
            1,
            'Expected the segment size to be 1 or greater. Got "-1"',
        ];

        yield 'invalid batch size (limit)' => [
            1,
            0,
            1,
            0,
            'Expected the batch size to be 1 or greater. Got "0"',
        ];

        yield 'invalid batch size' => [
            1,
            0,
            1,
            -1,
            'Expected the batch size to be 1 or greater. Got "-1"',
        ];

        yield 'segment size lower than batch size' => [
            1,
            0,
            1,
            10,
            'Expected the segment size ("1") to be greater or equal to the batch size ("10")',
        ];
    }

    private static function createInputArgs(
        bool $numberOfProcessesDefined,
        int $numberOfProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        int $expectedSegmentSize,
        int $expectedNumberOfSegments,
        int $expectedNumberOfBatches
    ): array {
        return func_get_args();
    }
}
