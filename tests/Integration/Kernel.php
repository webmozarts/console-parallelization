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

namespace Webmozarts\Console\Parallelization\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as HttpKernel;

final class Kernel extends HttpKernel
{
    public function __construct()
    {
        parent::__construct('dev', true);
    }

    /**
     * @return list<BundleInterface>
     */
    public function registerBundles(): array
    {
        return [
            new FrameworkBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(__DIR__.'/services.php');
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/var/cache/Kernel';
    }

    public function getLogDir(): string
    {
        return __DIR__.'/var/log/Kernel';
    }
}
