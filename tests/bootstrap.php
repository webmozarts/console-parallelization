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

// Workaround for https://github.com/symfony/symfony/issues/53812
// The problem is that FrameworkBundle registers an error handler but does not have a way to unregister it.
// The issue was closed, so we probably need to use the workaround until newer Symfony/PHPUnit versions fix it,
// or we use the regular Symfony WebTestCase instead of our custom WebRouteTestCase.
// The current setup code in FrameworkBundle will check if `ErrorHandler` is already registered and won't register it again.
use Symfony\Component\ErrorHandler\ErrorHandler;

require __DIR__.'/../vendor/autoload.php';

ErrorHandler::register();
