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

use function file_get_contents;
use function json_decode;
use const JSON_THROW_ON_ERROR;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Webmozarts\Console\Parallelization\ContainerAwareCommand;
use Webmozarts\Console\Parallelization\Integration\TestDebugProgressBarFactory;
use Webmozarts\Console\Parallelization\Integration\TestLogger;
use Webmozarts\Console\Parallelization\Logger\Logger;
use Webmozarts\Console\Parallelization\Logger\StandardLogger;
use Webmozarts\Console\Parallelization\Parallelization;

final class ImportMoviesCommand extends ContainerAwareCommand
{
    use Parallelization;

    protected static $defaultName = 'import:movies';

    private TestLogger $logger;

    /**
     * @var array<string, string>
     */
    private array $batchMovies;

    public function __construct()
    {
        parent::__construct(self::$defaultName);

        $this->logger = new TestLogger();
    }

    protected function configure(): void
    {
        self::configureParallelization($this);
    }

    /**
     * @return list<string>
     */
    protected function fetchItems(InputInterface $input): array
    {
        return [
            'movie-1.json',
            'movie-2.json',
            'movie-3.json',
            'movie-4.json',
            'movie-5.json',
        ];
    }

    protected function getSegmentSize(): int
    {
        return 2;
    }

    protected function runBeforeFirstCommand(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->recordFirstCommand();
    }

    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $movieFileNames
    ): void {
        $this->logger->recordBeforeBatch();

        $this->batchMovies = self::fetchMovieTitles($movieFileNames);
    }

    protected function runSingleCommand(string $movieFileName, InputInterface $input, OutputInterface $output): void
    {
        $this->logger->recordSingleCommand(
            $movieFileName,
            $this->batchMovies[$movieFileName],
        );
    }

    protected function runAfterBatch(InputInterface $input, OutputInterface $output, array $items): void
    {
        $this->logger->recordAfterBatch();

        unset($this->batchMovies);
    }

    protected function runAfterLastCommand(InputInterface $input, OutputInterface $output): void
    {
        $this->logger->recordLastCommand();
    }

    protected function getItemName(int $count): string
    {
        return 1 === $count ? 'movie' : 'movies';
    }

    protected function createLogger(OutputInterface $output): Logger
    {
        return new StandardLogger(
            $output,
            self::getProgressSymbol(),
            (new Terminal())->getWidth(),
            new TestDebugProgressBarFactory(),
            new ConsoleLogger($output),
        );
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
