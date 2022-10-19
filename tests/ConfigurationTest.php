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

/**
 * @covers \Webmozarts\Console\Parallelization\Configuration
 *
 * @internal
 */
final class ConfigurationTest extends TestCase
{
    /**
     * @dataProvider valuesProvider
     */
    public function test_it_can_be_instantiated(
        bool $shouldSpawnChildProcesses,
        ?int $numberOfItems,
        int $numberOfProcesses,
        int $segmentSize,
        int $batchSize,
        Configuration $expected
    ): void {
        $actual = Configuration::create(
            $shouldSpawnChildProcesses,
            $numberOfItems,
            $numberOfProcesses,
            $segmentSize,
            $batchSize,
        );

        self::assertEquals($expected, $actual);
    }

    public static function valuesProvider(): iterable
    {
        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[no child process] ',
            self::mainProcessValuesProvider(),
        );

        yield from PHPUnitProviderUtil::prefixWithLabel(
            '[child process(es)] ',
            self::childValuesProvider(),
        );
    }

    private static function mainProcessValuesProvider(): iterable
    {
        $createSet = static fn (
            ?int $numberOfItems,
            int $numberOfProcesses,
            int $batchSize,
            ?int $expectedTotalNumberOfBatches
        ) => [
            false,
            $numberOfItems,
            $numberOfProcesses,
            10,
            $batchSize,
            new Configuration(
                1,
                1,
                1,
                $expectedTotalNumberOfBatches,
            ),
        ];

        yield 'there is only one segment & one round' => [
            false,
            10,
            8,
            7,
            5,
            new Configuration(
                1,
                1,
                1,
                2,  // not interested in this value for this set
            ),
        ];

        yield 'no item' => $createSet(
            0,
            8,
            3,
            0,
        );

        yield 'all items can be processed within a single batch' => $createSet(
            1,
            8,
            2,
            1,
        );

        yield 'several batches are needed to process the items (exact)' => $createSet(
            4,
            8,
            2,
            2,
        );

        yield 'several batches are needed to process the items (not exact)' => $createSet(
            5,
            8,
            2,
            3,
        );

        yield 'several batches are needed to process the items (not exact - lower)' => $createSet(
            10,
            8,
            3,
            4,
        );

        yield 'unknown number of items' => $createSet(
            null,
            8,
            3,
            null,
        );
    }

    private static function childValuesProvider(): iterable
    {
        $createSet = static fn (
            ?int $numberOfItems,
            int $numberOfProcesses,
            int $segmentSize,
            int $batchSize,
            int $expectedNumberOfProcesses,
            ?int $expectedNumberOfSegments,
            ?int $expectedTotalNumberOfBatches
        ) => [
            true,
            $numberOfItems,
            $numberOfProcesses,
            $segmentSize,
            $batchSize,
            new Configuration(
                $expectedNumberOfProcesses,
                $segmentSize,
                $expectedNumberOfSegments,
                $expectedTotalNumberOfBatches,
            ),
        ];

        yield 'nominal' => [
            true,
            10,
            8,
            3,
            2,
            new Configuration(
                4,  // not interested in this value for this set
                3,
                4,  // not interested in this value for this set
                7,  // not interested in this value for this set
            ),
        ];

        yield 'all items can be processed within a single segment' => $createSet(
            3,
            8,
            5,
            5,
            1,
            1,
            1,
        );

        yield 'several segments are required to process the items (exact)' => $createSet(
            10,
            8,
            5,
            5,
            2,
            2,
            2,
        );

        yield 'several segments are required to process the items (not exact)' => $createSet(
            11,
            8,
            5,
            5,
            3,
            3,
            3,
        );

        yield 'all items can be processed within a single batch of a segment (exact)' => $createSet(
            10,
            8,
            5,
            5,
            2,
            2,
            2,
        );

        yield 'the items need to be processed within multiple batches of a segment (exact)' => $createSet(
            10,
            8,
            5,
            2,
            2,
            2,
            6,
        );

        yield 'the items need to be processed within multiple batches of a segment (not exact)' => $createSet(
            8,
            8,
            5,
            2,
            2,
            2,
            5,
        );

        yield 'the items need to be processed within multiple batches of a segment (not exact - lower)' => $createSet(
            50,
            5,
            10,
            3,
            5,
            5,
            20,
        );

        yield 'the items need to be processed within multiple batches of a segment (edge case)' => $createSet(
            10,
            8,
            5,
            1,
            2,
            2,
            10,
        );

        yield 'unknown number of items' => $createSet(
            null,
            8,
            5,
            1,
            8,
            null,
            null,
        );

        yield 'the number of processes is higher than the required number of processes' => $createSet(
            10,
            8,
            5,
            5,
            2,
            2,
            2,
        );

        yield 'the number of processes is the same as the required number of processes' => $createSet(
            10,
            2,
            5,
            5,
            2,
            2,
            2,
        );

        yield 'the number of processes is lower than the required number of processes' => $createSet(
            10,
            1,
            5,
            5,
            1,
            2,
            2,
        );
    }

    /**
     * @dataProvider invalidValuesProvider
     */
    public function test_it_cannot_be_instantiated_with_invalid_values(
        bool $shouldSpawnChildProcesses,
        ?int $numberOfItems,
        int $numberOfProcesses,
        int $segmentSize,
        int $batchSize,
        string $expectedErrorMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        Configuration::create(
            $shouldSpawnChildProcesses,
            $numberOfItems,
            $numberOfProcesses,
            $segmentSize,
            $batchSize,
        );
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'segment size lower than batch size (no child process)' => [
            false,
            0,
            8,
            1,
            10,
            'Expected the segment size ("1") to be greater or equal to the batch size ("10")',
        ];

        yield 'segment size lower than batch size (with child process)' => [
            true,
            0,
            8,
            1,
            10,
            'Expected the segment size ("1") to be greater or equal to the batch size ("10")',
        ];
    }
}
