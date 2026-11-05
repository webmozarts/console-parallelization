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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Webmozarts\Console\Parallelization\Fixtures\Counter;

return static function (ContainerConfigurator $container): void {
    // Since Symfony 8.1, the framework only registers this service when
    // symfony/event-dispatcher is a non-dev dependency. In this package it is
    // a dev dependency, so the test app must register it.
    $container->services()
        ->set('event_dispatcher', EventDispatcher::class)
        ->public()
        ->tag('container.hot_path')
        ->tag('event_dispatcher.dispatcher', ['name' => 'event_dispatcher']);

    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->load(
            'Webmozarts\Console\Parallelization\Fixtures\Command\\',
            __DIR__.'/../../Fixtures/Command',
        )
        ->set(Counter::class);
};
