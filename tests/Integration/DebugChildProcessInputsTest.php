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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Webmozarts\Console\Parallelization\Fixtures\Command\DebugChildProcessCommand;
use Webmozarts\Console\Parallelization\Integration\App\BareKernel;
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use function file_get_contents;

/**
 * @internal
 */
#[CoversNothing]
class DebugChildProcessInputsTest extends TestCase
{
    private DebugChildProcessCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->command = (new Application(new BareKernel()))->add(new DebugChildProcessCommand());
        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(DebugChildProcessCommand::OUTPUT_FILE);
    }

    #[DataProvider('inputProvider')]
    public function test_it_can_run_the_command_without_sub_processes(
        string $item,
        ?string $simpleOption,
        array $arrayOption,
        string $expected,
    ): void {
        $logger = new DummyLogger();

        $this->command->setItem($item);
        $this->command->setLogger($logger);

        $this->commandTester->execute(
            [
                'command' => 'debug:process',
                '--simple-option' => $simpleOption,
                '--array-option' => $arrayOption,
            ],
            ['interactive' => true],
        );

        $output = $this->commandTester->getDisplay();
        $actual = file_get_contents(DebugChildProcessCommand::OUTPUT_FILE);

        $this->commandTester->assertCommandIsSuccessful($output);
        self::assertSame($expected, $actual, $output);
    }

    public static function inputProvider(): iterable
    {
        // This test fails...
        // yield 'default' => [
        //     'item',
        //     null,
        //     [],
        //     DebugChildProcessCommand::createContent(
        //         'item',
        //         '',
        //         [],
        //     ),
        // ];

        yield 'with values' => [
            'item',
            'option',
            ['option1', 'option2'],
            DebugChildProcessCommand::createContent(
                'item',
                'option',
                ['option1--array-option=option2'],
            ),
        ];

        yield 'escaped string token' => [
            '"foo"',
            '"bar"',
            ['"option1"', '"option2"'],
            DebugChildProcessCommand::createContent(
                '"foo"',
                '"\"bar\""',
                ['"\"option1\""--array-option="\"option2\""'],
            ),
        ];

        yield 'escaped string token with both types of quotes' => [
            '"o_id in(\'20\')"',
            '"p_id in(\'22\')"',
            ['"option in(\'1\')"', '"option in(\'2\')"'],
            DebugChildProcessCommand::createContent(
                '"o_id in(\'20\')"',
                '"\"p_id in(\'22\')\""',
                ['"\"option in(\'1\')\""--array-option="\"option in(\'2\')\""'],
            ),
        ];

        yield 'with values with spaces' => [
            'a b c d',
            'd c b a',
            ['option 1', 'option 2'],
            DebugChildProcessCommand::createContent(
                'a b c d',
                '"d c b a"',
                ['"option 1"--array-option="option 2"'],
            ),
        ];
    }
}
