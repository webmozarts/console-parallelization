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

// config/services.php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webmozarts\Console\Parallelization\Fixtures\Counter;

return static function (ContainerConfigurator $container): void {
    // default configuration for services in *this* file
    $services = $container->services()
        ->defaults()
        ->autowire()      // Automatically injects dependencies in your services.
        ->autoconfigure(); // Automatically registers your services as commands, event subscribers, etc.

    // makes classes in src/ available to be used as services
    // this creates a service per class whose id is the fully-qualified class name
    $services
        ->load(
            'Webmozarts\\Console\\Parallelization\\Fixtures\\Command\\',
            __DIR__.'/../Fixtures/Command',
        )
        ->set(Counter::class);

    // order is important in this file because service definitions
    // always *replace* previous ones; add your own service configuration below
};
