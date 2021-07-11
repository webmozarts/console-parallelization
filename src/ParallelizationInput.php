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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Webmozart\Assert\Assert;
use function is_numeric;
use function sprintf;

final class ParallelizationInput
{
    private const ITEM_ARGUMENT = 'item';
    private const PROCESSES_OPTION = 'processes';
    private const CHILD_OPTION = 'child';

    private $numberOfProcessesDefined;
    private $numberOfProcesses = 1;
    private $item;
    private $childProcess;

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
                'The item to process'
            )
            ->addOption(
                self::PROCESSES_OPTION,
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
                null
            )
            ->addOption(
                self::CHILD_OPTION,
                null,
                InputOption::VALUE_NONE,
                'Set on child processes'
            )
        ;
    }

    public function __construct(InputInterface $input)
    {
        /** @var string|null $processes */
        $processes = $input->getOption(self::PROCESSES_OPTION);

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
        }

        /** @var string|null $item */
        $item = $input->getArgument(self::ITEM_ARGUMENT);

        $hasItem = null !== $item;

        if ($hasItem && !is_numeric($item)) {
            // Safeguard in case an invalid type is accidentally passed in tests when invoking the
            // command directly
            Assert::string($item);
        }

        $this->item = $hasItem ? (string) $item : null;

        $this->childProcess = (bool) $input->getOption('child');
    }

    public function isNumberOfProcessesDefined(): bool
    {
        return $this->numberOfProcessesDefined;
    }

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
