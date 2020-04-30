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
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozart\Assert\Assert;

abstract class ContainerAwareCommand extends Command implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface|null
     */
    private $container;

    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    protected function getContainer(): ContainerInterface
    {
        if (null !== $this->container) {
            return $this->container;
        }

        $application = $this->getApplication();

        Assert::notNull(
            $application,
            'The container cannot be retrieved as the application instance is not yet set'
        );

        return $this->container = $application->getKernel()->getContainer();
    }
}
