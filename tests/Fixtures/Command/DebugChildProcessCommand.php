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

namespace Webmozarts\Console\Parallelization\Fixtures\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\Logger\DummyLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use function file_put_contents;
use function func_get_args;
use const LOCK_EX;

final class DebugChildProcessCommand extends ParallelCommand
{
    public const string OUTPUT_FILE = __DIR__.'/../../../dist/debug-child-input.txt';

    private const string OPT_VALUE = 'optValue';

    private string $item = 'item';
    private ?Logger $logger = null;

    public function __construct()
    {
        parent::__construct('debug:process');
    }

    protected function configure(): void
    {
        parent::configure();

        $this->addOption(
            self::OPT_VALUE,
            null,
            InputOption::VALUE_OPTIONAL,
        );
    }

    public function setItem(string $item): void
    {
        $this->item = $item;
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        return [$this->item];
    }

    protected function configureParallelExecutableFactory(
        ParallelExecutorFactory $parallelExecutorFactory,
        InputInterface $input,
        OutputInterface $output,
    ): ParallelExecutorFactory {
        return parent::configureParallelExecutableFactory(...func_get_args())
            ->withScriptPath(__DIR__.'/../../../bin/console');
    }

    public function setLogger(?Logger $logger): void
    {
        $this->logger = $logger;
    }

    protected function createLogger(
        InputInterface $input,
        OutputInterface $output,
    ): Logger {
        return $this->logger ?? parent::createLogger($input, $output);
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        file_put_contents(
            self::OUTPUT_FILE,
            self::createContent(
                $item,
                $input->getOption(self::OPT_VALUE),
            ),
            flags: LOCK_EX,
        );
    }

    protected function getItemName(?int $count): string
    {
        return 1 === $count ? 'item' : 'items';
    }

    public static function createContent(
        string $item,
        ?string $option,
    ): string {
        return <<<EOF
            Item: {$item}
            Option: {$option}
            EOF;
    }
}
