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

namespace Webmozarts\Console\Parallelization\Process;

use DomainException;
use function func_get_args;
use Generator;
use function implode;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;

final class DummyProcess81 extends Process
{
    /**
     * @var array<array{string, array}>
     */
    public array $calls = [];

    /**
     * @var list<string>
     */
    public array $processedItems = [];

    private array $command;
    private bool $started = false;
    private bool $stopped = false;
    private InputStream $input;

    /**
     * @var Generator<string>
     */
    private Generator $inputIterator;

    /**
     * @var callable(string,string):void
     */
    private $callback;

    public function __construct(
        array $command,
        ?string $cwd = null,
        ?array $env = null,
        $input = null,
        ?float $timeout = 60
    ) {
        parent::__construct($command, $cwd, $env, $input, $timeout);

        $this->command = $command;
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __destruct()
    {
    }

    public function setTimeout(?float $timeout): static
    {
        parent::setTimeout($timeout);

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];

        return $this;
    }

    public function setInput($input): static
    {
        Assert::isInstanceOf($input, InputStream::class);
        $this->input = $input;

        $this->calls[] = [__FUNCTION__];

        return $this;
    }

    public function setEnv(array $env): static
    {
        parent::setEnv($env);

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];

        return $this;
    }

    public function start(?callable $callback = null, array $env = []): void
    {
        Assert::false($this->started);

        $this->started = true;
        $this->callback = $callback;
        $this->inputIterator = $this->input->getIterator();

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function isRunning(): bool
    {
        if ($this->started && $this->stopped) {
            return false;
        }

        $item = $this->inputIterator->current();

        if ('' === $item) {
            return false;
        }

        $this->inputIterator->next();
        $this->processedItems[] = $item;
        ($this->callback)('dummy', $item);

        return true;
    }

    public function stop(float $timeout = 10, ?int $signal = null): ?int
    {
    }

    public function run(?callable $callback = null, array $env = []): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function mustRun(?callable $callback = null, array $env = []): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function restart(?callable $callback = null, array $env = []): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function wait(?callable $callback = null): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function waitUntil(callable $callback): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getPid(): ?int
    {
        throw new DomainException('Unexpected call.');
    }

    public function signal(int $signal): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function disableOutput(): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function enableOutput(): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function isOutputDisabled(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getOutput(): string
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIncrementalOutput(): string
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIterator(int $flags = 0): Generator
    {
        throw new DomainException('Unexpected call.');
    }

    public function clearOutput(): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function getErrorOutput(): string
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIncrementalErrorOutput(): string
    {
        throw new DomainException('Unexpected call.');
    }

    public function clearErrorOutput(): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function getExitCode(): ?int
    {
        throw new DomainException('Unexpected call.');
    }

    public function getExitCodeText(): ?string
    {
        throw new DomainException('Unexpected call.');
    }

    public function isSuccessful(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function hasBeenSignaled(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getTermSignal(): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function hasBeenStopped(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStopSignal(): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function isStarted(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function isTerminated(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStatus(): string
    {
        throw new DomainException('Unexpected call.');
    }

    public function addOutput(string $line)
    {
        throw new DomainException('Unexpected call.');
    }

    public function addErrorOutput(string $line)
    {
        throw new DomainException('Unexpected call.');
    }

    public function getLastOutputTime(): ?float
    {
        throw new DomainException('Unexpected call.');
    }

    public function getCommandLine(): string
    {
        return implode(' ', $this->command);
    }

    public function getTimeout(): ?float
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIdleTimeout(): ?float
    {
        throw new DomainException('Unexpected call.');
    }

    public function setIdleTimeout(?float $timeout): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function setTty(bool $tty): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function isTty(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function setPty(bool $bool): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function isPty(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function setWorkingDirectory(string $cwd): static
    {
        throw new DomainException('Unexpected call.');
    }

    public function getInput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function checkTimeout(): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStartTime(): float
    {
        throw new DomainException('Unexpected call.');
    }

    public function setOptions(array $options): void
    {
        throw new DomainException('Unexpected call.');
    }

    public static function isTtySupported(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public static function isPtySupported(): bool
    {
        throw new DomainException('Unexpected call.');
    }
}
