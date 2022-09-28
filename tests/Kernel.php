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

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

final class Kernel extends HttpKernel
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        parent::__construct('dev', true);
    }

    /**
     * {@inheritdoc}
     */
    public function registerBundles(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
    }

    /**
     * {@inheritdoc}
     */
    protected function build(ContainerBuilder $container): void
    {
        $eventDispatcherDefinition = new Definition(
            EventDispatcher::class,
            []
        );
        $eventDispatcherDefinition->setPublic(true);

        $loggerDefinition = new Definition(
            ConsoleLogger::class,
            []
        );
        $loggerDefinition->setPublic(true);

        $container->addDefinitions([
            'event_dispatcher' => $eventDispatcherDefinition,
            'logger' => $loggerDefinition,
        ]);
    }
}
