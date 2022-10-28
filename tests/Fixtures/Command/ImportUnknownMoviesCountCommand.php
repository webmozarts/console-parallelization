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

use Error;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ErrorHandler\ErrorHandler;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Integration\TestLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\ParallelExecutorFactory;
use function file_get_contents;
use function json_decode;
use function realpath;
use const JSON_THROW_ON_ERROR;

final class ImportUnknownMoviesCountCommand extends ParallelCommand
{
    private TestLogger $logger;

    /**
     * @var array<string, string>
     */
    private array $batchMovies;

    public function __construct()
    {
        parent::__construct('import:movies-unknown-count');

        $this->logger = new TestLogger();
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): iterable
    {
        return yield from [
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
        ErrorHandler $errorHandler
    ): ParallelExecutorFactory {
        return ParallelExecutorFactory::create(
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
                fn ($input, $output, $movieFileNames) => $this->runBeforeBatch($movieFileNames),
            )
            ->withRunAfterBatch(
                fn ($input, $output, $movieFileNames) => $this->runAfterBatch(),
            )
            ->withRunAfterLastCommand(
                fn () => $this->logger->recordLastCommand(),
            )
            ->withScriptPath(realpath(__DIR__.'/../../../bin/console'));
    }

    protected function runSingleCommand(string $movieFileName, InputInterface $input, OutputInterface $output): void
    {
        if ('movie-4.json' === $movieFileName) {
            throw new Error('I FAILED!', 20);
        }

        $output->writeln('Hello there!');

        $this->logger->recordSingleCommand(
            $movieFileName,
            $this->batchMovies[$movieFileName],
        );
    }

    protected function getItemName(?int $count): string
    {
        if (null === $count) {
            return 'movie(s)';
        }

        return 1 === $count ? 'movie' : 'movies';
    }

    protected function createLogger(
        InputInterface $input,
        OutputInterface $output
    ): Logger
    {
        return new StandardLogger(
            $input, $output,
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
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
