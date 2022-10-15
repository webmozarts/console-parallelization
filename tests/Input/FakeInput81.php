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

namespace Webmozarts\Console\Parallelization\Input;

use DomainException;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;

final class FakeInput81 implements InputInterface
{
    public function __call($name, $arguments): void
    {
        throw new DomainException('Not implemented.');
    }

    public function getFirstArgument(): ?string
    {
        throw new DomainException('Not implemented.');
    }

    public function hasParameterOption($values, bool $onlyParams = false): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function getParameterOption(string|array $values, string|bool|int|float|array|null $default = false, bool $onlyParams = false): void
    {
        throw new DomainException('Not implemented.');
    }

    public function bind(InputDefinition $definition): void
    {
        throw new DomainException('Not implemented.');
    }

    public function validate(): void
    {
        throw new DomainException('Not implemented.');
    }

    public function getArguments(): array
    {
        throw new DomainException('Not implemented.');
    }

    public function getArgument(string $name): void
    {
        throw new DomainException('Not implemented.');
    }

    public function setArgument(string $name, $value): void
    {
        throw new DomainException('Not implemented.');
    }

    public function hasArgument(string $name): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function getOptions(): array
    {
        throw new DomainException('Not implemented.');
    }

    public function getOption(string $name): void
    {
        throw new DomainException('Not implemented.');
    }

    public function setOption(string $name, $value): void
    {
        throw new DomainException('Not implemented.');
    }

    public function hasOption(string $name): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function isInteractive(): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function setInteractive(bool $interactive): void
    {
        throw new DomainException('Not implemented.');
    }
}
