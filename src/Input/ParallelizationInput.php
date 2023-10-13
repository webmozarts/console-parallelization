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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\Process\CpuCoreCounter;
use function get_debug_type;
use function is_int;
use function is_numeric;
use function sprintf;

final class ParallelizationInput
{
    public const ITEM_ARGUMENT = 'item';
    public const PROCESSES_OPTION = 'processes';
    public const MAIN_PROCESS_OPTION = 'main-process';
    public const CHILD_OPTION = 'child';
    public const BATCH_SIZE = 'batch-size';
    public const SEGMENT_SIZE = 'segment-size';

    public const OPTIONS = [
        self::PROCESSES_OPTION,
        self::MAIN_PROCESS_OPTION,
        self::CHILD_OPTION,
    ];

    /**
     * @var positive-int
     */
    private int $numberOfProcesses;

    /**
     * @var callable():positive-int
     */
    private $findNumberOfProcesses;

    /**
     * @internal Use the static factory methods instead.
     *
     * @param positive-int|callable():positive-int $numberOfOrFindNumberOfProcesses
     * @param positive-int|null                    $batchSize
     * @param positive-int|null                    $segmentSize
     */
    public function __construct(
        private readonly bool $mainProcess,
        $numberOfOrFindNumberOfProcesses,
        private readonly ?string $item,
        private readonly bool $childProcess,
        private readonly ?int $batchSize,
        private readonly ?int $segmentSize
    ) {
        if (is_int($numberOfOrFindNumberOfProcesses)) {
            $this->numberOfProcesses = $numberOfOrFindNumberOfProcesses;
        } else {
            $this->findNumberOfProcesses = $numberOfOrFindNumberOfProcesses;
        }
    }

    public static function fromInput(InputInterface $input): self
    {
        /** @var string|null $numberOfProcesses */
        $numberOfProcesses = $input->getOption(self::PROCESSES_OPTION);
        /** @var mixed|null $item */
        $item = $input->getArgument(self::ITEM_ARGUMENT);
        $hasItem = null !== $item;
        /** @var bool $mainProcess */
        $mainProcess = $input->getOption(self::MAIN_PROCESS_OPTION);
        /** @var bool $isChild */
        $isChild = $input->getOption(self::CHILD_OPTION);
        /** @var string|int|null $batchSize */
        $batchSize = $input->getOption(self::BATCH_SIZE);
        /** @var string|int|null $segmentSize */
        $segmentSize = $input->getOption(self::SEGMENT_SIZE);

        if ($hasItem) {
            $item = self::validateItem($item);
            // When there is a single item passed, we do not want to spawn
            // child processes.
            $mainProcess = true;
        }

        if ($mainProcess) {
            // TODO: add this to the logger
            $validatedNumberOfProcesses = 1;
        } else {
            $validatedNumberOfProcesses = null !== $numberOfProcesses
                ? self::coerceNumberOfProcesses($numberOfProcesses)
                : static fn () => CpuCoreCounter::getNumberOfCpuCores();
        }

        if ($isChild) {
            Assert::false(
                $hasItem,
                sprintf(
                    'Cannot have an item passed to a child process as an argument. Got "%s"',
                    $item,
                ),
            );
        }

        $batchSize = self::coerceAndValidatePositiveInt(
            $batchSize,
            'batch size',
            true,
        );
        $segmentSize = self::coerceAndValidatePositiveInt(
            $segmentSize,
            'segment size',
            true,
        );

        return new self(
            $mainProcess,
            $validatedNumberOfProcesses,
            $hasItem ? $item : null,
            $isChild,
            $batchSize,
            $segmentSize,
        );
    }

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    public static function configureCommand(Command $command): void
    {
        $command
            ->addArgument(
                self::ITEM_ARGUMENT,
                InputArgument::OPTIONAL,
                'The item to process.',
            )
            ->addOption(
                self::PROCESSES_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of maximum parallel child processes to run.',
            )
            ->addOption(
                self::MAIN_PROCESS_OPTION,
                'm',
                InputOption::VALUE_NONE,
                'To execute the processing in the main process (no child processes will be spawned).',
            )
            ->addOption(
                self::CHILD_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Set on child processes.',
            )
            ->addOption(
                self::BATCH_SIZE,
                null,
                InputOption::VALUE_REQUIRED,
                'Set the batch size.',
            )
            ->addOption(
                self::SEGMENT_SIZE,
                null,
                InputOption::VALUE_REQUIRED,
                'Set the segment size.',
            );
    }

    public function shouldBeProcessedInMainProcess(): bool
    {
        return $this->mainProcess;
    }

    /**
     * @return positive-int
     */
    public function getNumberOfProcesses(): int
    {
        if (!isset($this->numberOfProcesses)) {
            $this->numberOfProcesses = ($this->findNumberOfProcesses)();
        }

        return $this->numberOfProcesses;
    }

    public function getItem(): ?string
    {
        return $this->item;
    }

    public function isChildProcess(): bool
    {
        return $this->childProcess;
    }

    /**
     * @return positive-int|null
     */
    public function getBatchSize(): ?int
    {
        return $this->batchSize;
    }

    /**
     * @return positive-int|null
     */
    public function getSegmentSize(): ?int
    {
        return $this->segmentSize;
    }

    private static function validateItem(mixed $item): string
    {
        if (is_numeric($item)) {
            return (string) $item;
        }

        // Safeguard in case an invalid type is accidentally passed in tests when invoking the
        // command directly
        Assert::string(
            $item,
            sprintf(
                'Invalid item type. Expected a string, got "%s".',
                get_debug_type($item),
            ),
        );

        return $item;
    }

    /**
     * @return positive-int
     */
    private static function coerceNumberOfProcesses(string $numberOfProcesses): int
    {
        return self::coerceAndValidatePositiveInt(
            $numberOfProcesses,
            'maximum number of parallel processes',
            false,
        );
    }

    /**
     * @return ($nullable is true ? positive-int|null : positive-int)
     */
    private static function coerceAndValidatePositiveInt(
        null|int|string $value,
        string $name,
        bool $nullable
    ): ?int {
        if ($nullable && null === $value) {
            return null;
        }

        $message = sprintf(
            'Expected the %s to be an integer greater than or equal to 1. Got "%s".',
            $name,
            $value,
        );

        Assert::integerish($value, $message);

        $value = (int) $value;

        Assert::positiveInteger($value, $message);

        return $value;
    }
}
