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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportMoviesCommand extends ContainerAwareCommand
{
    use Parallelization;

    protected static $defaultName = 'import:movies';

    public function __construct(?string $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        self::configureParallelization($this);
    }

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
    }

    protected function runBeforeBatch(
        InputInterface $input,
        OutputInterface $output,
        array $items
    ): void {
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        // insert into the database
    }

    protected function runAfterBatch(InputInterface $input, OutputInterface $output, array $items): void
    {
        // flush the database and clear the entity manager
    }

    protected function getItemName(int $count): string
    {
        return 1 === $count ? 'movie' : 'movies';
    }
}
