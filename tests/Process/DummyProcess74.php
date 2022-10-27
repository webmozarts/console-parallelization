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
use Generator;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;
use function func_get_args;
use function implode;

final class DummyProcess74 extends Process
{
    /** @readonly */
    public int $index;

    private int $pid;

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

    private int $exitCode;

    public function __construct(
        int $index,
        int $pid,
        array $command,
        int $exitCode,
        ?string $cwd = null,
        ?array $env = null,
        $input = null,
        ?float $timeout = 60
    ) {
        parent::__construct($command, $cwd, $env, $input, $timeout);

        $this->index = $index;
        $this->pid = $pid;
        $this->command = $command;
        $this->exitCode = $exitCode;
    }

    /** @noinspection MagicMethodsValidityInspection */
    public function __destruct()
    {
    }

    public function setTimeout($timeout)
    {
        parent::setTimeout($timeout);

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];

        return $this;
    }

    public function setInput($input)
    {
        Assert::isInstanceOf($input, InputStream::class);
        $this->input = $input;

        $this->calls[] = [__FUNCTION__];

        return $this;
    }

    public function setEnv(array $env)
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
            $this->stopped = true;

            return false;
        }

        $this->inputIterator->next();
        $this->processedItems[] = $item;
        ($this->callback)('dummy', $item);

        return true;
    }

    public function stop($timeout = 10, $signal = null): ?int
    {
    }

    public function run(?callable $callback = null, array $env = []): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function mustRun(?callable $callback = null, array $env = []): self
    {
        throw new DomainException('Unexpected call.');
    }

    public function restart(?callable $callback = null, array $env = []): self
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
        return $this->started && !$this->stopped ? $this->pid : null;
    }

    public function signal($signal): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function disableOutput(): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function enableOutput(): void
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

    public function getIterator($flags = 0): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function clearOutput(): void
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

    public function clearErrorOutput(): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function getExitCode(): ?int
    {
        return $this->stopped ? $this->exitCode : null;
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

    public function addOutput(string $line): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function addErrorOutput(string $line): void
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

    public function setIdleTimeout($timeout): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function setTty($tty): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function isTty(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function setPty($bool): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function isPty(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function setWorkingDirectory($cwd): void
    {
        throw new DomainException('Unexpected call.');
    }

    public function getInput(): void
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
