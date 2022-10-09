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

use Fidry\Console\Command\Configuration;
use Fidry\Console\Input\IO;
use function gettype;
use function is_numeric;
use function sprintf;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;

final class ParallelizationInput
{
    private const ITEM_ARGUMENT = 'item';
    private const PROCESSES_OPTION = 'processes';
    private const CHILD_OPTION = 'child';

    private bool $numberOfProcessesDefined;

    /**
     * @var positive-int
     */
    private int $numberOfProcesses;

    private ?string $item;
    private bool $childProcess;

    /**
     * @param positive-int $numberOfProcesses
     */
    public function __construct(
        bool $numberOfProcessesDefined,
        int $numberOfProcesses,
        ?string $item,
        bool $childProcess
    ) {
        $this->numberOfProcessesDefined = $numberOfProcessesDefined;
        $this->numberOfProcesses = $numberOfProcesses;
        $this->item = $item;
        $this->childProcess = $childProcess;
    }

    public static function fromInput(IO $io): self
    {
        $numberOfProcesses = $io->getOption(self::PROCESSES_OPTION)->asNullablePositiveInteger();
        $item = $io->getArgument(self::ITEM_ARGUMENT)->asNullableString();
        $isChild = $io->getOption(self::CHILD_OPTION)->asBoolean();

        $numberOfProcessesDefined = null !== $numberOfProcesses;
        $numberOfProcesses = $numberOfProcesses ?? 1;

        Assert::greaterThan(
            $numberOfProcesses,
            0,
            sprintf(
                'Expected the number of processes to be 1 or greater. Got "%s".',
                $numberOfProcesses,
            ),
        );

        $hasItem = null !== $item;

        if ($hasItem && !is_numeric($item)) {
            // Safeguard in case an invalid type is accidentally passed in tests when invoking the
            // command directly
            Assert::string(
                $item,
                sprintf(
                    'Invalid item type. Expected a string, got "%s".',
                    // TODO: change to get_debug_type() once dropping PHP 7.4
                    gettype($item),
                ),
            );
        }

        return new self(
            $numberOfProcessesDefined,
            $numberOfProcesses,
            $hasItem ? (string) $item : null,
            $isChild,
        );
    }

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    public static function createConfiguration(
        string $name,
        string $description,
        string $help
    ): Configuration
    {
        return new Configuration(
            $name,
            $description,
            $help,
            [
                new InputArgument(
                    self::ITEM_ARGUMENT,
                    InputArgument::OPTIONAL,
                    'The item to process',
                ),
            ],
            [
                new InputOption(
                    self::PROCESSES_OPTION,
                    'p',
                    InputOption::VALUE_OPTIONAL,
                    'The number of parallel processes to run',
                ),
                new InputOption(
                    self::CHILD_OPTION,
                    null,
                    InputOption::VALUE_NONE,
                    'Set on child processes',
                ),
            ],
        );
    }

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    public static function configureParallelization(Command $command): void
    {
        $command
            ->addArgument(
                self::ITEM_ARGUMENT,
                InputArgument::OPTIONAL,
                'The item to process',
            )
            ->addOption(
                self::PROCESSES_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
            )
            ->addOption(
                self::CHILD_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Set on child processes',
            );
    }

    public function isNumberOfProcessesDefined(): bool
    {
        return $this->numberOfProcessesDefined;
    }

    /**
     * @return positive-int
     */
    public function getNumberOfProcesses(): int
    {
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
}
