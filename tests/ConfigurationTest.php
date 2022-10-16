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
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        int $expectedSegmentSize,
        int $expectedNumberOfSegments,
        int $expectedTotalNumberOfBatches
    ): void {
        $config = new Configuration(
            $shouldSpawnChildProcesses,
            $numberOfItems,
            $segmentSize,
            $batchSize,
        );

        self::assertSame($expectedSegmentSize, $config->getSegmentSize());
        self::assertSame($expectedNumberOfSegments, $config->getNumberOfSegments());
        self::assertSame($expectedTotalNumberOfBatches, $config->getTotalNumberOfBatches());
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
            int $numberOfItems,
            int $batchSize,
            int $expectedTotalNumberOfBatches
        ) => [
            false,
            $numberOfItems,
            10,
            $batchSize,
            1,
            1,
            $expectedTotalNumberOfBatches,
        ];

        yield 'there is only one segment & one round' => [
            false,
            10,
            7,
            5,
            1,
            1,
            2,  // not interested in this value for this set
        ];

        yield 'no item' => $createSet(
            0,
            3,
            0,
        );

        yield 'all items can be processed within a single batch' => $createSet(
            1,
            2,
            1,
        );

        yield 'several batches are needed to process the items (exact)' => $createSet(
            4,
            2,
            2,
        );

        yield 'several batches are needed to process the items (not exact)' => $createSet(
            5,
            2,
            3,
        );
    }

    private static function childValuesProvider(): iterable
    {
        $createSet = static fn (
            int $numberOfItems,
            int $segmentSize,
            int $batchSize,
            int $expectedNumberOfSegments,
            int $expectedTotalNumberOfBatches
        ) => [
            true,
            $numberOfItems,
            $segmentSize,
            $batchSize,
            $segmentSize,
            $expectedNumberOfSegments,
            $expectedTotalNumberOfBatches,
        ];

        yield 'nominal' => [
            true,
            10,
            3,
            2,
            3,
            4,  // not interested in this value for this set
            7,  // not interested in this value for this set
        ];

        yield 'all items can be processed within a single segment' => $createSet(
            3,
            5,
            5,
            1,
            1,
        );

        yield 'several segments are required to process the items (exact)' => $createSet(
            10,
            5,
            5,
            2,
            2,
        );

        yield 'several segments are required to process the items (not exact)' => $createSet(
            11,
            5,
            5,
            3,
            3,
        );

        yield 'all items can be processed within a single batch of a segment (exact)' => $createSet(
            10,
            5,
            5,
            2,
            2,
        );

        yield 'the items need to be processed within multiple batches of a segment (exact)' => $createSet(
            10,
            5,
            2,
            2,
            6,
        );

        yield 'the items need to be processed within multiple batches of a segment (not exact)' => $createSet(
            8,
            5,
            2,
            2,
            5,
        );

        yield 'the items need to be processed within multiple batches of a segment (edge case)' => $createSet(
            10,
            5,
            1,
            2,
            10,
        );
    }

    /**
     * @dataProvider invalidValuesProvider
     */
    public function test_it_cannot_be_instantiated_with_invalid_values(
        bool $shouldSpawnChildProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        string $expectedErrorMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        new Configuration(
            $shouldSpawnChildProcesses,
            $numberOfItems,
            $segmentSize,
            $batchSize,
        );
    }

    public static function invalidValuesProvider(): iterable
    {
        yield 'segment size lower than batch size (no child process)' => [
            false,
            0,
            1,
            10,
            'Expected the segment size ("1") to be greater or equal to the batch size ("10")',
        ];

        yield 'segment size lower than batch size (with child process)' => [
            true,
            0,
            1,
            10,
            'Expected the segment size ("1") to be greater or equal to the batch size ("10")',
        ];
    }
}
