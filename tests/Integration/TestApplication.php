<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Integration;

use Fidry\Console\Application\Application;
use Webmozarts\Console\Parallelization\Fixtures\Command\ImportMoviesCommand;
use Webmozarts\Console\Parallelization\Fixtures\Command\NoSubProcessCommand;

final class TestApplication implements Application
{
    public function getName(): string
    {
        return 'TestApp';
    }

    public function getVersion(): string
    {
        return '2.0.0';
    }

    public function getLongVersion(): string
    {
        return '2.0.0-unstable';
    }

    public function getHelp(): string
    {
        return '';
    }

    public function getCommands(): array
    {
        return [
            new ImportMoviesCommand(),
            new NoSubProcessCommand(),
        ];
    }

    public function getDefaultCommand(): string
    {
        return 'unknown';
    }

    public function isAutoExitEnabled(): bool
    {
        return true;
    }

    public function areExceptionsCaught(): bool
    {
        return true;
    }
}
