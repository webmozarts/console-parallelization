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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use Webmozarts\Console\Parallelization\Parallelization;
use Webmozarts\Console\Parallelization\Process\PhpExecutableFinder;
use function chr;
use function func_get_args;
use function spl_object_id;
use function sprintf;

/**
 * Command implementing the 1.x API fully. The goal is to better assess the
 * upgrade path & deprecation layer.
 */
final class LegacyCommand extends Command
{
    use Parallelization {
        execute as originalExecute;
        configureParallelization as originalConfigureParallelization;
        getEnvironmentVariables as originalGetEnvironmentVariables;
        runBeforeFirstCommand as originalRunBeforeFirstCommand;
        runAfterLastCommand as originalRunAfterLastCommand;
        runBeforeBatch as originalRunBeforeBatch;
        runAfterBatch as originalRunAfterBatch;
        getSegmentSize as originalGetSegmentSize;
        getBatchSize as originalGetBatchSize;
        getConsolePath as originalGetConsolePath;
    }

    /**
     * @var array<string, array{string, mixed[]}>
     */
    public static array $calls = [];

    private const string DRY_RUN_OPT = 'dry-run';

    private readonly ContainerInterface $container;

    public function __construct()
    {
        parent::__construct();

        $legacyContainer = new Container();
        $legacyContainer->setParameter('kernel.project_dir', __DIR__.'./..');
        $legacyContainer->setParameter('kernel.debug', true);
        $legacyContainer->setParameter('kernel.environment', 'test');

        $this->container = $legacyContainer;
    }

    protected function configure(): void
    {
        $this->setName('legacy:command');
        $this->setDescription('Command to test the legacy API.');
        $this->addOption(
            self::DRY_RUN_OPT,
            null,
            InputOption::VALUE_NONE,
        );

        self::configureParallelization($this);
    }

    /**
     * @deprecated remove me once Parallelization has been decoupled from the container
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function fetchItems(InputInterface $input, OutputInterface $output): array
    {
        $items = [];

        for ($i = 0; $i < 20; ++$i) {
            $items["i{$i}"] = "item{$i}";
        }

        return $items;
    }

    public function getItemName(?int $count): string
    {
        return 1 === $count ? 'legacy item' : 'legacy items';
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];

        if (self::isDryRun($input)) {
            return $this->executeDryRun($input, $output);
        }

        $this->originalExecute($input, $output);

        $parallelizationInput = ParallelizationInput::fromInput($input);

        if (!$parallelizationInput->isChildProcess()) {
            $output->writeln('You may need to run this command again.');
        }

        return 0;
    }

    protected function runSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];
    }

    private static function isDryRun(InputInterface $input): bool
    {
        return (bool) $input->getOption(self::DRY_RUN_OPT);
    }

    private function executeDryRun(InputInterface $input, OutputInterface $output): int
    {
        $items = $this->fetchItems($input, $output);

        foreach ($items as $index => $item) {
            $output->writeln(
                sprintf(
                    'Processed in dry run item #%s: %s',
                    $index,
                    $item,
                ),
            );
        }

        return 0;
    }

    private static function getProgressSymbol(): string
    {
        self::$calls['static'][] = [__FUNCTION__, func_get_args()];

        return chr(200);
    }

    private static function detectPhpExecutable(): string
    {
        self::$calls['static'][] = [__FUNCTION__, func_get_args()];

        return PhpExecutableFinder::find().' -d memory_limit=-1';
    }

    private static function getWorkingDirectory(ContainerInterface $container): string
    {
        self::$calls['static'][] = [__FUNCTION__, func_get_args()];

        return __DIR__;
    }

    protected static function configureParallelization(Command $command): void
    {
        self::$calls['static'][] = [__FUNCTION__, func_get_args()];

        self::originalConfigureParallelization($command);
    }

    protected function getEnvironmentVariables(ContainerInterface $container): array
    {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];

        return $this->originalGetEnvironmentVariables($container);
    }

    protected function runBeforeFirstCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];
    }

    protected function runAfterLastCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];
    }

    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];
    }

    protected function runAfterBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];
    }

    protected function getSegmentSize(): int
    {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];

        return $this->originalGetSegmentSize();
    }

    protected function getBatchSize(): int
    {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];

        return $this->originalGetBatchSize();
    }

    protected function getConsolePath(): string
    {
        self::$calls[spl_object_id($this)][] = [__FUNCTION__, func_get_args()];

        return $this->originalGetConsolePath();
    }
}
