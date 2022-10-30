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

use Generator;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Webmozart\Assert\Assert;
use Webmozarts\Console\Parallelization\UnexpectedCall;
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
        ($this->callback)($this->index, $this->getPid(), 'dummy', $item);

        return true;
    }

    public function stop($timeout = 10, $signal = null): ?int
    {
    }

    public function run(?callable $callback = null, array $env = []): int
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function mustRun(?callable $callback = null, array $env = []): self
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function restart(?callable $callback = null, array $env = []): self
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function wait(?callable $callback = null): int
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function waitUntil(callable $callback): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getPid(): ?int
    {
        return $this->started && !$this->stopped ? $this->pid : null;
    }

    public function signal($signal): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function disableOutput(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function enableOutput(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isOutputDisabled(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getOutput(): string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getIncrementalOutput(): string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getIterator($flags = 0): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function clearOutput(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getErrorOutput(): string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getIncrementalErrorOutput(): string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function clearErrorOutput(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getExitCode(): ?int
    {
        return $this->stopped ? $this->exitCode : null;
    }

    public function getExitCodeText(): ?string
    {
        return 'No explanation, this is a dummy text.';
    }

    public function isSuccessful(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function hasBeenSignaled(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getTermSignal(): int
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function hasBeenStopped(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getStopSignal(): int
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isStarted(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isTerminated(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getStatus(): string
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function addOutput(string $line): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function addErrorOutput(string $line): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getLastOutputTime(): ?float
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getCommandLine(): string
    {
        return implode(' ', $this->command);
    }

    public function getTimeout(): ?float
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getIdleTimeout(): ?float
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setIdleTimeout($timeout): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setTty($tty): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isTty(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setPty($bool): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function isPty(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setWorkingDirectory($cwd): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getInput(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function checkTimeout(): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function getStartTime(): float
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public function setOptions(array $options): void
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public static function isTtySupported(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }

    public static function isPtySupported(): bool
    {
        throw UnexpectedCall::forMethod(__METHOD__);
    }
}
