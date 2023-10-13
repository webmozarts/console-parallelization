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

use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Webmozarts\Console\Parallelization\UnexpectedCall;

final class FakeInput implements InputInterface
{
    public function __call($name, $arguments): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getFirstArgument(): ?string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function hasParameterOption($values, bool $onlyParams = false): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getParameterOption(array|string $values, null|array|bool|float|int|string $default = false, bool $onlyParams = false): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function bind(InputDefinition $definition): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function validate(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getArguments(): array
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getArgument(string $name): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setArgument(string $name, $value): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function hasArgument(string $name): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getOptions(): array
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getOption(string $name): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setOption(string $name, $value): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function hasOption(string $name): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isInteractive(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setInteractive(bool $interactive): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
