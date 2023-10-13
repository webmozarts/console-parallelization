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
use function max;
use function min;
use function sprintf;

final class Configuration
{
    /**
     * @internal Use the static factory methods instead.
     *
     * @param positive-int        $numberOfProcesses
     * @param positive-int        $segmentSize
     * @param positive-int|null   $numberOfSegments
     * @param positive-int|0|null $totalNumberOfBatches
     */
    public function __construct(
        private readonly int $numberOfProcesses,
        private readonly int $segmentSize,
        private readonly ?int $numberOfSegments,
        private readonly ?int $totalNumberOfBatches,
    ) {
    }

    /**
     * @param 0|positive-int|null $numberOfItems
     * @param positive-int        $numberOfProcesses
     * @param positive-int        $segmentSize
     * @param positive-int        $batchSize
     */
    public static function create(
        bool $shouldSpawnChildProcesses,
        ?int $numberOfItems,
        int $numberOfProcesses,
        int $segmentSize,
        int $batchSize,
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

        return $shouldSpawnChildProcesses
            ? self::createForWithChildProcesses(
                $numberOfItems,
                $numberOfProcesses,
                $segmentSize,
                $batchSize,
            )
            : self::createForInMainProcesses(
                $numberOfItems,
                $batchSize,
            );
    }

    /**
     * @codeCoverageIgnore
     *
     * @return positive-int
     */
    public function getNumberOfProcesses(): int
    {
        return $this->numberOfProcesses;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return positive-int
     */
    public function getSegmentSize(): int
    {
        return $this->segmentSize;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return positive-int|null
     */
    public function getNumberOfSegments(): ?int
    {
        return $this->numberOfSegments;
    }

    /**
     * @codeCoverageIgnore
     *
     * @return positive-int|0|null
     */
    public function getTotalNumberOfBatches(): ?int
    {
        return $this->totalNumberOfBatches;
    }

    /**
     * @param 0|positive-int|null $numberOfItems
     * @param positive-int        $batchSize
     */
    private static function createForInMainProcesses(
        ?int $numberOfItems,
        int $batchSize
    ): self {
        // The segments are what define the sizes of the sub-processes. When
        // executing only the main process, then there is no use for
        // segments.
        // See https://github.com/webmozarts/console-parallelization#segments

        $totalNumberOfBatches = null === $numberOfItems
            ? null
            : (int) ceil($numberOfItems / $batchSize);
        Assert::nullOrNatural($totalNumberOfBatches);

        return new self(
            1,
            1,
            1,
            $totalNumberOfBatches,
        );
    }

    /**
     * @param 0|positive-int|null $numberOfItems
     * @param positive-int        $numberOfProcesses
     * @param positive-int        $segmentSize
     * @param positive-int        $batchSize
     */
    private static function createForWithChildProcesses(
        ?int $numberOfItems,
        int $numberOfProcesses,
        int $segmentSize,
        int $batchSize
    ): self {
        if (null === $numberOfItems) {
            return new self(
                $numberOfProcesses,
                $segmentSize,
                null,
                null,
            );
        }

        $numberOfSegments = max(1, (int) ceil($numberOfItems / $segmentSize));
        Assert::positiveInteger($numberOfSegments);

        $numberOfSegmentsRequired = max(1, (int) ceil($numberOfItems / $segmentSize));
        Assert::positiveInteger($numberOfSegmentsRequired);

        $requiredNumberOfProcesses = min($numberOfProcesses, $numberOfSegmentsRequired);
        Assert::positiveInteger($requiredNumberOfProcesses);

        return new self(
            $requiredNumberOfProcesses,
            $segmentSize,
            $numberOfSegments,
            self::calculateTotalNumberOfBatches(
                $numberOfItems,
                $segmentSize,
                $batchSize,
                $numberOfSegments,
            ),
        );
    }

    /**
     * @param 0|positive-int $numberOfItems
     * @param positive-int   $segmentSize
     * @param positive-int   $batchSize
     * @param positive-int   $numberOfSegments
     *
     * @return 0|positive-int
     */
    private static function calculateTotalNumberOfBatches(
        int $numberOfItems,
        int $segmentSize,
        int $batchSize,
        int $numberOfSegments
    ): int {
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
