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

use Fidry\Console\Command\Configuration as ConsoleConfiguration;
use Fidry\Console\Input\IO;
use function array_merge;
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
    private $numberOfProcesses;

    private ?string $item;
    private bool $childProcess;

    public function __construct(IO $io)
    {
        $numberOfProcesses = $io->getOption(self::PROCESSES_OPTION)->asNullablePositiveInteger();
        $this->numberOfProcesses = $numberOfProcesses ?? 1;
        $this->numberOfProcessesDefined = null !== $numberOfProcesses;
        $this->item = $io->getArgument(self::ITEM_ARGUMENT)->asNullableString();
        $this->childProcess = $io->getOption(self::CHILD_OPTION)->asBoolean();
    }

    /**
     * Beware that if the command is lazy, the name and description will be
     * overwritten by the values provided for the laziness (see the LazyCommand
     * API).
     *
     * @param InputArgument[] $arguments
     * @param InputOption[]   $options
     */
    public static function createConfiguration(
        string $name,
        string $description,
        string $help,
        array $arguments = [],
        array $options = []
    ): ConsoleConfiguration
    {
        return new ConsoleConfiguration(
            $name,
            $description,
            $help,
            array_merge(
                $arguments,
                self::createArguments(),
            ),
            array_merge(
                $options,
                self::createOptions(),
            ),
        );
    }

    /**
     * @return list<InputArgument>
     */
    public static function createArguments(): array
    {
        return [
            new InputArgument(
                self::ITEM_ARGUMENT,
                InputArgument::OPTIONAL,
                'The item to process.',
            ),
        ];
    }

    /**
     * @return list<InputOption>
     */
    public static function createOptions(): array
    {
        return [
            new InputOption(
                self::PROCESSES_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run.',
                null,
            ),
            new InputOption(
                self::CHILD_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Set for child processes.',
            ),
        ];
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
