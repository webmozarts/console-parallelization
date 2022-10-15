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

final class FakeInput74 implements InputInterface
{
    public function __call($name, $arguments)
    {
        throw new DomainException('Not implemented.');
    }

    public function getFirstArgument(): ?string
    {
        throw new DomainException('Not implemented.');
    }

    public function hasParameterOption($values, $onlyParams = false): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function getParameterOption($values, $default = false, $onlyParams = false)
    {
        throw new DomainException('Not implemented.');
    }

    public function bind(InputDefinition $definition)
    {
        throw new DomainException('Not implemented.');
    }

    public function validate()
    {
        throw new DomainException('Not implemented.');
    }

    public function getArguments(): array
    {
        throw new DomainException('Not implemented.');
    }

    public function getArgument($name)
    {
        throw new DomainException('Not implemented.');
    }

    public function setArgument($name, $value)
    {
        throw new DomainException('Not implemented.');
    }

    public function hasArgument($name): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function getOptions(): array
    {
        throw new DomainException('Not implemented.');
    }

    public function getOption($name)
    {
        throw new DomainException('Not implemented.');
    }

    public function setOption($name, $value)
    {
        throw new DomainException('Not implemented.');
    }

    public function hasOption($name): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function isInteractive(): bool
    {
        throw new DomainException('Not implemented.');
    }

    public function setInteractive($interactive)
    {
        throw new DomainException('Not implemented.');
    }
}
