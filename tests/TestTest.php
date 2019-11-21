<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use function feof;
use function fgets;
use function preg_replace;
use function rewind;
use function str_replace;

class TestTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * @var CommandTester
     */
    private $commandTester;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $command = new ImportMoviesCommand();

        $this->application = new Application(
            new class('dev', true) extends Kernel {
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
            }
        );
        $this->application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function test_it_can_run_the_command_without_parallel(): void
    {
        $this->commandTester->execute(
            ['command' => 'import:movies'],
            ['interactive' => true]
        );

        $actual = $this->commandTester->getDisplay(true);

        $this->assertSame('', $actual, 'Expected logs to be identical');
    }

    /**
     * Returns the output for the tester.
     */
    protected function getOutput(CommandTester $tester): string
    {
        /** @var StreamOutput $output */
        $output = $tester->getOutput();
        $stream = $output->getStream();
        $string = '';

        rewind($stream);

        while (false === feof($stream)) {
            $string .= fgets($stream);
        }

        $string = preg_replace(
            [
                '/\x1b(\[|\(|\))[;?0-9]*[0-9A-Za-z]/',
                '/[\x03|\x1a]/',
            ],
            ['', '', ''],
            $string
        );

        return str_replace(PHP_EOL, "\n", $string);
    }
}