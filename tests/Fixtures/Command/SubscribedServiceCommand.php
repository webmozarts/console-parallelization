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

namespace Webmozarts\Console\Parallelization\Fixtures\Command;

use LogicException;
use Override;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;
use UnexpectedValueException;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\ErrorHandler\ResetServiceErrorHandler;
use Webmozarts\Console\Parallelization\Fixtures\Counter;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use function array_map;
use function range;

final class SubscribedServiceCommand extends ParallelCommand
{
    private bool $threwOnce = false;

    public function __construct()
    {
        parent::__construct('subscribed-service');
    }

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // Ensure this command cannot be run with child processes. The purpose of this command
        // is to test that the service counter is properly reset, doing this with child processes
        // would require to persist its state across processes which would be needlessly complicated.
        $input->setOption(ParallelizationInput::MAIN_PROCESS_OPTION, true);
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return array_map(
            strval(...),
            range(0, 3),
        );
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }

    #[Override]
    protected function configureParallelExecutableFactory(
        ParallelExecutorFactory $parallelExecutorFactory,
        InputInterface $input,
        OutputInterface $output,
    ): ParallelExecutorFactory {
        return $parallelExecutorFactory
            ->withBatchSize(2)
            ->withRunAfterLastCommand($this->checkCounter(...));
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $counter = $this->counter();

        if ($counter->getCount() >= 2 && !$this->threwOnce) {
            $this->threwOnce = true;

            throw new UnexpectedValueException('3rd item reached.');
        }

        $counter->increment();
    }

    private function checkCounter(): void
    {
        $counter = $this->counter();
        $count = $counter->getCount();

        if ($count >= 2) {
            throw new LogicException('The Counter service was not correctly reset.');
        }
    }

    #[SubscribedService]
    private function counter(): Counter
    {
        return $this->container->get(__METHOD__);
    }

    #[Override]
    protected function createErrorHandler(InputInterface $input, OutputInterface $output): ErrorHandler
    {
        return ResetServiceErrorHandler::forContainer($this->getContainer());
    }
}
