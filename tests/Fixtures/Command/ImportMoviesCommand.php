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

use Fidry\Console\Command\Command;
use Fidry\Console\Command\Configuration;
use Fidry\Console\Input\IO;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandlerLogger;
use Webmozarts\Console\Parallelization\ErrorHandler\NullErrorHandler;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;
use function file_get_contents;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use function realpath;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ErrorHandler\ItemProcessingErrorHandler;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Integration\TestLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use Webmozarts\Console\Parallelization\Parallelization;

final class ImportMoviesCommand implements Command
{
    use Parallelization {
        getParallelExecutableFactory as getOriginalParallelExecutableFactory;
    }

    private TestLogger $logger;

    /**
     * @var array<string, string>
     */
    private array $batchMovies;

    public function __construct()
    {
        $this->logger = new TestLogger();
    }

    public function getName(): string
    {
        return 'import:movies';
    }

    public function getConfiguration(): Configuration
    {
        return ParallelizationInput::createConfiguration(
            $this->getName(),
            'Imports movies.',
            'Is for test purposes.',
        );
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(IO $io): array
    {
        return [
            'movie-1.json',
            'movie-2.json',
            'movie-3.json',
            'movie-4.json',
            'movie-5.json',
        ];
    }

    protected function getParallelExecutableFactory(
        callable $fetchItems,
        callable $runSingleCommand,
        callable $getItemName,
        string $commandName,
        InputDefinition $commandDefinition,
        ItemProcessingErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        return $this
            ->getOriginalParallelExecutableFactory(
                $fetchItems,
                $runSingleCommand,
                $getItemName,
                $commandName,
                $commandDefinition,
                $errorHandler,
            )
            ->withBatchSize(2)
            ->withSegmentSize(2)
            ->withRunBeforeFirstCommand(
                fn () => $this->logger->recordFirstCommand(),
            )
            ->withRunBeforeBatch(
                fn ($io, $movieFileNames) => $this->runBeforeBatch($movieFileNames),
            )
            ->withRunAfterBatch(
                fn ($io, $movieFileNames) => $this->runAfterBatch(),
            )
            ->withRunAfterLastCommand(
                fn () => $this->logger->recordLastCommand(),
            )
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    protected function runSingleCommand(string $movieFileName, IO $io): void
    {
        $this->logger->recordSingleCommand(
            $movieFileName,
            $this->batchMovies[$movieFileName],
        );
    }

    protected function getItemName(int $count): string
    {
        return 1 === $count ? 'movie' : 'movies';
    }

    protected function createLogger(IO $io): Logger
    {
        return new StandardLogger(
            $io,
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
            new ConsoleLogger($io->getOutput()),
        );
    }

    protected function createItemErrorHandler(): ItemProcessingErrorHandler
    {
        return new ItemProcessingErrorHandlerLogger(
            new NullErrorHandler(),
        );
    }

    private function runBeforeBatch(
        array $movieFileNames
    ): void {
        $this->logger->recordBeforeBatch();

        $this->batchMovies = self::fetchMovieTitles($movieFileNames);
    }

    private function runAfterBatch(): void
    {
        $this->logger->recordAfterBatch();

        unset($this->batchMovies);
    }

    /**
     * @param list<string> $movieFileNames
     *
     * @return array<string, string>
     */
    private static function fetchMovieTitles(array $movieFileNames): array
    {
        $movies = [];

        foreach ($movieFileNames as $movieFileName) {
            $moviePath = __DIR__.'/../movies/'.$movieFileName;

            $decodedContent = json_decode(
                file_get_contents($moviePath),
                null,
                512,
                JSON_THROW_ON_ERROR,
            );

            $movies[$movieFileName] = $decodedContent->title;
        }

        return $movies;
    }
}
