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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozarts\Console\Parallelization\Parallelization;

/**
 * Command relying on the defaults of the 1.x API, i.e. it does not override
 * any of the deprecated hooks.
 */
final class DefaultLegacyCommand extends Command
{
    use Parallelization;

    private readonly ContainerInterface $container;

    public function __construct()
    {
        parent::__construct();

        $legacyContainer = new Container();
        $legacyContainer->setParameter('kernel.project_dir', __DIR__.'./..');
        $legacyContainer->setParameter('kernel.debug', true);
        $legacyContainer->setParameter('kernel.environment', 'test');

        $this->container = $legacyContainer;
    }

    protected function configure(): void
    {
        $this->setName('default-legacy:command');

        self::configureParallelization($this);
    }

    /**
     * @deprecated remove me once Parallelization has been decoupled from the container
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return ['item0', 'item1'];
    }

    public function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }

    protected function runSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void {
    }
}
