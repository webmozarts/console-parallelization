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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;
use function array_values;
use function ceil;
use function count;
use function gettype;
use function is_int;
use function is_numeric;
use function sprintf;

final class ParallelizationInput
{
    private const ITEM = 'item';
    private const PROCESSES = 'processes';
    private const CHILD = 'child';

    private $numberOfProcessesDefined;
    private $numberOfProcesses = 1;
    private $items;
    private $itemsCount;
    private $segmentSize;
    private $batchSize;
    private $rounds;
    private $batches;

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    public static function configureParallelization(Command $command): void
    {
        $command
            ->addArgument(
                self::ITEM,
                InputArgument::OPTIONAL,
                'The item to process'
            )
            ->addOption(
                self::PROCESSES,
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
                null
            )
            ->addOption(
                self::CHILD,
                null,
                InputOption::VALUE_NONE,
                'Set on child processes'
            )
        ;
    }

    /**
     * @param Closure(InputInterface): string[] $itemsFetcher
     */
    public function __construct(
        InputInterface $input,
        Closure $itemsFetcher,
        int $segmentSize,
        int $batchSize
    ) {
        /** @var string|null $processes */
        $processes = $input->getOption(self::PROCESSES);

        $this->numberOfProcessesDefined = null !== $processes;

        if ($this->numberOfProcessesDefined) {
            Assert::numeric(
                $processes,
                sprintf(
                    'Expected the number of process defined to be a valid numeric value. Got "%s"',
                    $processes
                )
            );

            $this->numberOfProcesses = (int) $processes;

            Assert::same(
                // We cast it again in string to make sure since it is more convenient to pass an
                // int in the tests or when calling the command directly without passing by the CLI
                (string) $processes,
                (string) $this->numberOfProcesses,
                sprintf(
                    'Expected the number of process defined to be an integer. Got "%s"',
                    $processes
                )
            );

            Assert::greaterThan(
                $this->numberOfProcesses,
                0,
                sprintf(
                    'Requires at least one process. Got "%s"',
                    $this->numberOfProcesses
                )
            );
        }

        /** @var string|null $item */
        $item = $input->getArgument(self::ITEM);

        $hasItem = null !== $item;

        if ($hasItem && !is_int($item)) {
            // Safeguard in case an invalid type is accidentally passed in tests when invoking the
            // command directly
            Assert::string($item);
        }

        $this->items = $hasItem
            // We cast it again in case another value was passed when invoking the command
            // directly in the tests
            ? [(string) $item]
            : self::retrieveItems($input, $itemsFetcher)
        ;
        $this->itemsCount = count($this->items);

        $this->segmentSize = 1 === $this->numberOfProcesses && !$this->numberOfProcessesDefined ? $this->itemsCount : $segmentSize;
        $this->batchSize = $batchSize;
        $this->rounds = (int) (1 === $this->numberOfProcesses ? 1 : ceil($this->itemsCount / $segmentSize));
        $this->batches = (int) (ceil($segmentSize / $batchSize) * $this->rounds);

        if (!$hasItem && 1 !== $this->numberOfProcesses) {
            // Shouldn't check this when only one item has been specified or
            // when no child processes is used
            Assert::greaterThanEq(
                $segmentSize,
                $batchSize,
                sprintf(
                    'The segment size ("%s") should always be greater or equal to the batch size ("%s")',
                    $segmentSize,
                    $batchSize
                )
            );
        }
    }

    public function isNumberOfProcessesDefined(): bool
    {
        return $this->numberOfProcessesDefined;
    }

    public function getNumberOfProcesses(): int
    {
        return $this->numberOfProcesses;
    }

    /**
     * @return list<string>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getItemsCount(): int
    {
        return $this->itemsCount;
    }

    public function getSegmentSize(): int
    {
        return $this->segmentSize;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getRounds(): int
    {
        return $this->rounds;
    }

    public function getBatches(): int
    {
        return $this->batches;
    }

    /**
     * @param Closure(InputInterface): string[] $itemsFetcher
     *
     * @return list<string>
     */
    private static function retrieveItems(InputInterface $input, Closure $itemsFetcher): array
    {
        $items = $itemsFetcher($input);

        Assert::isArray(
            $items,
            sprintf(
                'Expected the fetched items to be a list of strings. Got "%s"',
                gettype($items)
            )
        );

        foreach ($items as $index => $item) {
            if (is_numeric($item)) {
                $items[$index] = (string) $item;

                continue;
            }

            Assert::string(
                $item,
                sprintf(
                    'The items are potentially passed to the child processes via the STDIN. For this reason they are expected to be string values. Got "%s" for the item "%s"',
                    gettype($item),
                    $index
                )
            );
        }

        return array_values($items);
    }
}
