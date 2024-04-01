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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversNothing]
class SubscribedServicesIntegrationTest extends TestCase
{
    private CommandTester $subscribedServiceCommandTester;

    protected function setUp(): void
    {
        $this->subscribedServiceCommandTester = new CommandTester(
            (new Application(new Kernel()))->get('subscribed-service')
        );
    }

    public function test_it_can_a_command_with_subscribed_services(): void
    {
        $commandTester = $this->subscribedServiceCommandTester;

        $commandTester->execute(
            ['command' => 'subscribed-service'],
            ['interactive' => true],
        );

        $commandTester->assertCommandIsSuccessful();
    }
}
