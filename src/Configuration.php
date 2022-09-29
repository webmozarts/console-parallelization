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

use function ceil;
use function sprintf;
use Webmozart\Assert\Assert;

final class Configuration
{
    /**
     * @var positive-int
     */
    private int $segmentSize;

    /**
     * @var positive-int
     */
    private int $numberOfSegments;

    /**
     * @var positive-int
     */
    private int $numberOfBatches;

    /**
     * @param positive-int $numberOfProcesses
     * @param 0|positive-int $numberOfItems
     * @param positive-int $segmentSize
     * @param positive-int $batchSize
     */
    public function __construct(
        bool $numberOfProcessesDefined,
        int $numberOfProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize
    ) {
        // We always check those (and not the calculated ones) since they come from the command
        // configuration so an issue there hints on a misconfiguration which should be fixed.
        Assert::greaterThanEq(
            $segmentSize,
            $batchSize,
            sprintf(
                'Expected the segment size ("%s") to be greater or equal to the batch size ("%s")',
                $segmentSize,
                $batchSize,
            ),
        );

        $this->segmentSize = 1 === $numberOfProcesses && !$numberOfProcessesDefined
            ? $numberOfItems
            : $segmentSize;
        $this->numberOfSegments = (int) (1 === $numberOfProcesses ? 1 : ceil($numberOfItems / $segmentSize));
        $this->numberOfBatches = (int) (ceil($segmentSize / $batchSize) * $this->numberOfSegments);
    }

    /**
     * @return positive-int
     */
    public function getSegmentSize(): int
    {
        return $this->segmentSize;
    }

    /**
     * @return positive-int
     */
    public function getNumberOfSegments(): int
    {
        return $this->numberOfSegments;
    }

    /**
     * @return positive-int
     */
    public function getNumberOfBatches(): int
    {
        return $this->numberOfBatches;
    }
}
