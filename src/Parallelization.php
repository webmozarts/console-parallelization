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

use function array_diff_key;
use function array_fill_keys;
use function array_filter;
use function array_merge;
use function array_slice;
use function implode;
use RuntimeException;
use function sprintf;
use const STDIN;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ResettableContainerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use function trim;
use Webmozart\Assert\Assert;

/**
 * Adds parallelization capabilities to console commands.
 *
 * Make sure to call configureParallelization() in your configure() method!
 *
 * You must implement the following methods in your command:
 *
 *  * fetchItems(): Returns all the items that you want to process as
 *    strings. Typically, you will return IDs of database objects here.
 *  * runSingleCommand(): Executes the command for a single item.
 *  * getItemName(): Returns a human readable name of the processed items.
 *
 * You can improve the performance of your command by making use of batching.
 * Batching allows you to process multiple items together, for example to
 * persist them in a batch to reduce the number of I/O operations.
 *
 * To enable batching, you will typically implement runAfterBatch() and persist
 * the changes done in multiple calls of runSingleCommand().
 *
 * The batch size is determined by getBatchSize() and defaults to the segment
 * size. The segment size is the number of items a worker (child) process
 * consumes before it dies. This means that, by default, a child process will
 * process all its items, persist them in a batch and then die. If you want
 * to improve the performance of your command, try to tweak getSegmentSize()
 * first. Optionally, you can tweak getBatchSize() to process multiple batches
 * in each child process.
 */
trait Parallelization
{
    private $logError = true;

    /**
     * Returns the symbol for communicating progress from the child to the
     * master process when displaying the progress bar.
     */
    private static function getProgressSymbol(): string
    {
        return chr(254);
    }

    /**
     * Detects the path of the PHP interpreter.
     */
    private static function detectPhpExecutable(): string
    {
        $php = (new PhpExecutableFinder())->find();

        if (false === $php) {
            throw new RuntimeException('Cannot find php executable');
        }

        return $php;
    }

    /**
     * Returns the environment variables that are passed to the child processes.
     *
     * @param ContainerInterface $container The service containers
     *
     * @return string[] A list of environment variable names and values
     */
    private static function getEnvironmentVariables(ContainerInterface $container): array
    {
        return [
            'PATH' => getenv('PATH'),
            'HOME' => getenv('HOME'),
            'SYMFONY_DEBUG' => $container->getParameter('kernel.debug'),
            'SYMFONY_ENV' => $container->getParameter('kernel.environment'),
        ];
    }

    /**
     * Returns the working directory for the child process.
     *
     * @param ContainerInterface $container The service container
     *
     * @return string The absolute path to the working directory
     */
    private static function getWorkingDirectory(ContainerInterface $container): string
    {
        return dirname($container->getParameter('kernel.root_dir'));
    }

    /**
     * Adds the command configuration specific to parallelization.
     *
     * Call this method in your configure() method.
     */
    protected static function configureParallelization(Command $command): void
    {
        $command
            ->addArgument(
                'item',
                InputArgument::OPTIONAL,
                'The item to process'
            )
            ->addOption(
                'processes',
                'p',
                InputOption::VALUE_OPTIONAL,
                'The number of parallel processes to run',
                null
            )
            ->addOption(
                'child',
                null,
                InputOption::VALUE_NONE,
                'Set on child processes'
            )
        ;
    }

    /**
     * Provided by Symfony Command class.
     *
     * @return string The command name
     */
    abstract public function getName();

    /**
     * Provided by Symfony Command class.
     *
     * @return ContainerInterface
     */
    abstract protected function getContainer();

    /**
     * Provided by Symfony Command class.
     *
     * @return Application
     */
    abstract protected function getApplication();

    /**
     * Fetches the items that should be processed.
     *
     * Typically, you will fetch all the items of the database objects that
     * you want to process here. These will be passed to runSingleCommand().
     *
     * This method is called exactly once in the master process.
     *
     * @param InputInterface $input The console input
     *
     * @return string[] The items to process
     */
    abstract protected function fetchItems(InputInterface $input): array;

    /**
     * Processes an item in the child process.
     */
    abstract protected function runSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void;

    /**
     * Returns the name of each item in lowercase letters.
     *
     * For example, this method could return "contact" if the count is one and
     * "contacts" otherwise.
     *
     * @param int $count The number of items
     *
     * @return string The name of the item in the correct plurality
     */
    abstract protected function getItemName(int $count): string;

    /**
     * Method executed at the very beginning of the master process.
     */
    protected function runBeforeFirstCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * Method executed at the very end of the master process.
     */
    protected function runAfterLastCommand(
        InputInterface $input,
        OutputInterface $output
    ): void {
    }

    /**
     * Method executed before executing all the items of the current batch.
     * This method is executed in both the master and child process.
     *
     * @param string[] $items
     */
    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * Method executed after executing all the items of the current batch.
     * This method is executed in both the master and child process.
     *
     * @param string[] $items
     */
    protected function runAfterBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    /**
     * Returns the number of items to process per child process. This is
     * done in order to circumvent some issues recurring to long living
     * processes such as memory leaks.
     *
     * This value is only relevant when ran with child process(es).
     */
    protected function getSegmentSize(): int
    {
        return 50;
    }

    /**
     * Returns the number of items to process in a batch. Multiple batches
     * can be executed within the master and child processes. This allows to
     * early fetch aggregates or persist aggregates in batches for performance
     * optimizations.
     */
    protected function getBatchSize(): int
    {
        return $this->getSegmentSize();
    }

    /**
     * Executes the parallelized command.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('child')) {
            $this->executeChildProcess($input, $output);

            return 0;
        }

        $this->executeMasterProcess($input, $output);

        return 0;
    }

    /**
     * Executes the master process.
     *
     * The master process spawns as many child processes as set in the
     * "--processes" option. Each of the child processes receives a segment of
     * items of the processed data set and terminates. As long as there is data
     * left to process, new child processes are spawned automatically.
     */
    protected function executeMasterProcess(InputInterface $input, OutputInterface $output): void
    {
        $this->runBeforeFirstCommand($input, $output);

        $numberOfProcessesDefined = null !== $input->getOption('processes');
        $numberOfProcesses = $numberOfProcessesDefined ? (int) $input->getOption('processes') : 1;
        $hasItem = (bool) $input->getArgument('item');
        $items = $hasItem ? [$input->getArgument('item')] : $this->fetchItems($input);
        $count = count($items);
        $segmentSize = 1 === $numberOfProcesses && !$numberOfProcessesDefined ? $count : $this->getSegmentSize();
        $batchSize = $this->getBatchSize();
        $rounds = 1 === $numberOfProcesses ? 1 : ceil($count * 1.0 / $segmentSize);
        $batches = ceil($segmentSize * 1.0 / $batchSize) * $rounds;

        Assert::greaterThan(
            $numberOfProcesses,
            0,
            sprintf(
                'Requires at least one process. Got "%s"',
                $input->getOption('processes')
            )
        );

        if (!$hasItem && 1 !== $numberOfProcesses) {
            // Shouldn't check this when only one item has been specified or
            // when no child processes is used
            Assert::greaterThanEq(
                $segmentSize,
                $batchSize,
                sprintf(
                    'The segment size should always be greater or equal to '
                    .'the batch size. Got respectively "%d" and "%d"',
                    $segmentSize,
                    $batchSize
                )
            );
        }

        $output->writeln(sprintf(
            'Processing %d %s in segments of %d, batches of %d, %d %s, %d %s in %d %s',
            $count,
            $this->getItemName($count),
            $segmentSize,
            $batchSize,
            $rounds,
            1 === $rounds ? 'round' : 'rounds',
            $batches,
            1 === $batches ? 'batch' : 'batches',
            $numberOfProcesses,
            1 === $numberOfProcesses ? 'process' : 'processes'
        ));
        $output->writeln('');

        $progressBar = new ProgressBar($output, $count);
        $progressBar->setFormat('debug');
        $progressBar->start();

        if ($count <= $segmentSize || (1 === $numberOfProcesses && !$numberOfProcessesDefined)) {
            // Run in the master process

            $itemsChunks = array_chunk(
                $items,
                $this->getBatchSize(),
                false
            );

            foreach ($itemsChunks as $items) {
                $this->runBeforeBatch($input, $output, $items);

                foreach ($items as $item) {
                    $this->runTolerantSingleCommand((string) $item, $input, $output);

                    $progressBar->advance();
                }

                $this->runAfterBatch($input, $output, $items);
            }
        } else {
            // Distribute if we have multiple segments
            $consolePath = $this->getConsolePath();
            Assert::fileExists(
                $consolePath,
                sprintf('The bin/console file could not be found at %s', getcwd()))
            ;

            $commandTemplate = implode(
                ' ',
                array_merge(
                    array_filter([
                        self::detectPhpExecutable(),
                        $consolePath,
                        $this->getName(),
                        implode(' ', array_slice($input->getArguments(), 1)),
                        '--child',
                    ]),
                    $this->serializeInputOptions($input, ['child', 'processes'])
                )
            );
            $terminalWidth = (new Terminal())->getWidth();

            $processLauncher = new ProcessLauncher(
                $commandTemplate,
                self::getWorkingDirectory($this->getContainer()),
                self::getEnvironmentVariables($this->getContainer()),
                $numberOfProcesses,
                $segmentSize,
                $this->getContainer()->get('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
                function (string $type, string $buffer) use ($progressBar, $output, $terminalWidth) {
                    $this->processChildOutput($buffer, $progressBar, $output, $terminalWidth);
                }
            );

            $processLauncher->run($items);
        }

        $progressBar->finish();

        $output->writeln('');
        $output->writeln('');
        $output->writeln(sprintf(
            'Processed %d %s.',
            $count,
            $this->getItemName($count)
        ));

        $this->runAfterLastCommand($input, $output);
    }
    
    /**
     * Get the path of the executable Symfony bin console.
     */
    protected function getConsolePath() : string {
        return realpath(getcwd().'/bin/console');
    }

    /**
     * Executes the child process.
     *
     * This method reads the items from the standard input that the master process
     * piped into the process. These items are passed to runSingleCommand() one
     * by one.
     */
    protected function executeChildProcess(
        InputInterface $input,
        OutputInterface $output
    ): void {
        $advancementChar = self::getProgressSymbol();

        $itemsChunks = array_chunk(
            array_filter(
                explode(
                    PHP_EOL,
                    stream_get_contents(STDIN)
                )
            ),
            $this->getBatchSize()
        );

        foreach ($itemsChunks as $items) {
            $this->runBeforeBatch($input, $output, $items);

            foreach ($items as $item) {
                $this->runTolerantSingleCommand($item, $input, $output);

                $output->write($advancementChar);
            }

            $this->runAfterBatch($input, $output, $items);
        }
    }

    /**
     * Called whenever data is received in the master process from a child process.
     *
     * @param string          $buffer        The received data
     * @param ProgressBar     $progressBar   The progress bar
     * @param OutputInterface $output        The output of the master process
     * @param int             $terminalWidth The width of the terminal window
     *                                       in characters
     */
    private function processChildOutput(
        string $buffer,
        ProgressBar $progressBar,
        OutputInterface $output,
        int $terminalWidth
    ): void {
        $advancementChar = self::getProgressSymbol();
        $chars = mb_substr_count($buffer, $advancementChar);

        // Display unexpected output
        if ($chars !== mb_strlen($buffer)) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<comment>%s</comment>',
                str_pad(' Process Output ', $terminalWidth, '=', STR_PAD_BOTH)
            ));
            $output->writeln(str_replace($advancementChar, '', $buffer));
            $output->writeln('');
        }

        $progressBar->advance($chars);
    }

    private function runTolerantSingleCommand(
        string $item,
        InputInterface $input,
        OutputInterface $output
    ): void {
        try {
            $this->runSingleCommand(trim($item), $input, $output);
        } catch (Throwable $exception) {
            if ($this->logError) {
                $output->writeln(sprintf(
                    "Failed to process \"%s\": %s\n%s",
                    trim($item),
                    $exception->getMessage(),
                    $exception->getTraceAsString()
                ));
            }

            $container = $this->getContainer();

            if (
                (class_exists(ResetInterface::class) && $container instanceof ResetInterface)
                || (class_exists(ResettableContainerInterface::class) && $container instanceof ResettableContainerInterface)
            ) {
                $container->reset();
            }
        }
    }
    
    /**
     * @param string[] $blackListParams
     * @return string[]
     */
    private function serializeInputOptions(InputInterface $input, array $blackListParams) : array {
        $options = array_diff_key(
            array_filter($input->getOptions()),
            array_fill_keys($blackListParams, '')
        );

        $preparedOptionList = [];
        foreach ($options as $name => $value) {
            $definition = $this->getDefinition();
            $option = $definition->getOption($name);

            $optionString  = "";
            if (!$option->acceptValue()) {
                $optionString .= ' --' . $name;
            } elseif ($option->isArray()) {
                foreach ($value as $arrayValue) {
                    $optionString .= ' --'.$name.'='.$this->quoteOptionValue($arrayValue);
                }
            } else {
                $optionString .= ' --'.$name.'='.$this->quoteOptionValue($value);
            }

            $preparedOptionList[] = $optionString;
        }
        return $preparedOptionList;
    }

    /**
     * Ensure that an option value is quoted correctly, before it is passed to a child process.
     * @param mixed $value the input option value, which is typically a string but can be of any other primitive type.
     * @return mixed the replaced and quoted value, if $value contained a character that required quoting.
     */
    protected function quoteOptionValue($value) {

        if($this->isValueRequiresQuoting($value)) {
            return sprintf('"%s"', str_replace('"', '\"', $value));
        } else {
            return $value;
        }
    }

    /**
     * Validate whether a command option requires quoting or not, depending on its content.
     */
    protected function isValueRequiresQuoting($value) : bool {
        return 0 < preg_match('/[\s \\\\ \' " & | < > = ! @]/x', $value);
    }
}
