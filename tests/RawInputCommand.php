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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand(
    name: 'raw-input',
    description: 'A command to demonstrate the RawInput class',
)]
final class RawInputCommand extends Command
{
    private const ARG_NAME_1 = 'arg1';
    private const ARG_NAME_2 = 'arg1';

    public function configure(): void
    {
        $this->addArgument(
            self::ARG_NAME_1,
            InputArgument::REQUIRED,
            'The first argument',
        );
        $this->addArgument(
            self::ARG_NAME_2,
            InputArgument::REQUIRED,
            'The first argument',
        );
    }
}
