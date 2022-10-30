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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Webmozarts\Console\Parallelization\Fixtures\Command\NoItemCommand;

/**
 * @coversNothing
 *
 * @internal
 */
class NoItemCommandTest extends TestCase
{
    private Command $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->command = (new Application())->add(new NoItemCommand());
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        unset($this->command, $this->commandTester);
    }

    public function test_it_can_execute_a_command_that_gives_no_item(): void
    {
        $this->commandTester->execute(
            ['command' => 'test:no-item'],
            ['interactive' => true],
        );

        $expected = <<<'EOF'
            Processing 0 item in segments of 50, batches of 50, 1 round, 0 batch, with 1 child process.

                0 [>---------------------------] 10 secs 10.0 MiB

             // Memory usage: 10.0 MB (peak: 10.0 MB), time: 10 secs

            Processed 0 item.

            EOF;

        $actual = OutputNormalizer::removeIntermediateFixedProgressBars(
            $this->getOutput($this->commandTester),
        );

        self::assertSame($expected, $actual, $actual);
    }

    private function getOutput(CommandTester $commandTester): string
    {
        $output = $commandTester->getDisplay(true);

        return OutputNormalizer::normalize($output);
    }
}
