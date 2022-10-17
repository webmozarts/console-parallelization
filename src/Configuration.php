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

use Webmozart\Assert\Assert;
use function ceil;
use function sprintf;

final class Configuration
{
    /**
     * @var positive-int
     */
    private int $segmentSize;

    /**
     * @var positive-int|null
     */
    private ?int $numberOfSegments;

    /**
     * @var positive-int|0|null
     */
    private ?int $totalNumberOfBatches;

    /**
     * @param 0|positive-int|null $numberOfItems
     * @param positive-int        $segmentSize
     * @param positive-int        $batchSize
     */
    public static function create(
        bool $shouldSpawnChildProcesses,
        ?int $numberOfItems,
        int $segmentSize,
        int $batchSize
    ): self {
        // We always check those (and not the calculated ones) since they come from the command
        // configuration so an issue there hints on a misconfiguration which should be fixed.
        Assert::greaterThanEq(
            $segmentSize,
            $batchSize,
            sprintf(
                'Expected the segment size ("%s") to be greater or equal to the batch size ("%s").',
                $segmentSize,
                $batchSize,
            ),
        );

        if ($shouldSpawnChildProcesses) {
            $segmentSize = $segmentSize;
            /** @var positive-int $numberOfSegments */
            $numberOfSegments = (int) ceil($numberOfItems / $segmentSize);
            $totalNumberOfBatches = self::calculateTotalNumberOfBatches(
                $numberOfItems,
                $segmentSize,
                $batchSize,
                $numberOfSegments,
            );
        } else {
            // The segments are what define the sizes of the sub-processes. When
            // executing only the main process, then there is no use for
            // segments.
            // See https://github.com/webmozarts/console-parallelization#segments
            $segmentSize = 1;
            $numberOfSegments = 1;
            /** @var positive-int|0 $totalNumberOfBatches */
            $totalNumberOfBatches = (int) ceil($numberOfItems / $batchSize);
        }

        return new self(
            $segmentSize,
            $numberOfSegments,
            $totalNumberOfBatches,
        );
    }

    /**
     * @param positive-int        $segmentSize
     * @param positive-int|null   $numberOfSegments
     * @param positive-int|0|null $totalNumberOfBatches
     */
    public function __construct(
        int $segmentSize,
        ?int $numberOfSegments,
        ?int $totalNumberOfBatches
    ) {
        $this->segmentSize = $segmentSize;
        $this->numberOfSegments = $numberOfSegments;
        $this->totalNumberOfBatches = $totalNumberOfBatches;
    }

    /**
     * @return positive-int
     */
    public function getSegmentSize(): int
    {
        return $this->segmentSize;
    }

    /**
     * @return positive-int|null
     */
    public function getNumberOfSegments(): ?int
    {
        return $this->numberOfSegments;
    }

    /**
     * @return positive-int|0|null
     */
    public function getTotalNumberOfBatches(): ?int
    {
        return $this->totalNumberOfBatches;
    }

    /**
     * @param 0|positive-int|null $numberOfItems
     * @param positive-int        $segmentSize
     * @param positive-int        $batchSize
     * @param positive-int        $numberOfSegments
     *
     * @return 0|positive-int
     */
    private static function calculateTotalNumberOfBatches(
        ?int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        int $numberOfSegments
    ): int {
        if (null == $numberOfItems) {
            return 0;
        }

        if ($numberOfSegments >= 2) {
            // It "should" be `$numberOfSegments - 1`. However, it actually does
            // not matter as the expression L128 is just going to give a
            // negative number adjusting the final result correctly.
            // So we keep this simpler expression, although a bit less intuitive,
            // to avoid to have to configure Infection to not mutate this piece.
            $numberOfCompleteSegments = $numberOfSegments;
            $totalNumberOfBatches = ((int) ceil($segmentSize / $batchSize)) * $numberOfSegments;
        } else {
            $numberOfCompleteSegments = 0;
            $totalNumberOfBatches = 0;
        }

        $totalNumberOfBatches += (int) ceil(($numberOfItems - $numberOfCompleteSegments * $segmentSize) / $batchSize);
        Assert::natural($totalNumberOfBatches);

        return $totalNumberOfBatches;
    }
}
