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

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class ImportMoviesCommand extends ContainerAwareCommand
{
    use Parallelization;

    /**
     * {@inheritdoc}
     */
    protected static $defaultName = 'import:movies';

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        self::configureParallelization($this);
    }

    /**
     * {@inheritdoc}
     */
    protected function fetchItems(InputInterface $input): array
    {
        // open up the file and read movie data...

        // return items as strings
        return [
            '{"id": 1, "name": "Star Wars"}',
            '{"id": 2, "name": "Django Unchained"}',
            // ...
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        // insert into the database
    }

    /**
     * {@inheritdoc}
     */
    protected function runAfterBatch(InputInterface $input, OutputInterface $output, array $items): void
    {
        // flush the database and clear the entity manager
    }

    /**
     * {@inheritdoc}
     */
    protected function getItemName(int $count): string
    {
        return 1 === $count ? 'movie' : 'movies';
    }
}
