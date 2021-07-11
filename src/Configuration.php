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
    private $segmentSize;
    private $rounds;
    private $batches;

    public function __construct(
        bool $numberOfProcessesDefined,
        int $numberOfProcesses,
        int $numberOfItems,
        int $segmentSize,
        int $batchSize
    ) {
        Assert::greaterThan(
            $numberOfProcesses,
            0,
            sprintf(
                'Expected the number of processes to be 1 or greater. Got "%s"',
                $numberOfProcesses
            )
        );
        Assert::natural(
            $numberOfItems,
            sprintf(
                'Expected the number of items to be 0 or greater. Got "%s"',
                $numberOfItems
            )
        );
        Assert::greaterThan(
            $segmentSize,
            0,
            sprintf(
                'Expected the segment size to be 1 or greater. Got "%s"',
                $segmentSize
            )
        );
        Assert::greaterThan(
            $batchSize,
            0,
            sprintf(
                'Expected the batch size to be 1 or greater. Got "%s"',
                $batchSize
            )
        );

        // We always check those (and not the calculated ones) since they come from the command
        // configuration so an issue there hints on a misconfiguration which should be fixed.
        Assert::greaterThanEq(
            $segmentSize,
            $batchSize,
            sprintf(
                'Expected the segment size ("%s") to be greater or equal to the batch size ("%s")',
                $segmentSize,
                $batchSize
            )
        );

        $this->segmentSize = 1 === $numberOfProcesses && !$numberOfProcessesDefined
            ? $numberOfItems
            : $segmentSize
        ;
        $this->rounds = (int) (1 === $numberOfProcesses ? 1 : ceil($numberOfItems / $segmentSize));
        $this->batches = (int) (ceil($segmentSize / $batchSize) * $this->rounds);
    }

    public function getSegmentSize(): int
    {
        return $this->segmentSize;
    }

    public function getNumberOfSegments(): int
    {
        return $this->rounds;
    }

    public function getNumberOfBatches(): int
    {
        return $this->batches;
    }
}
