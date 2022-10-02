<?php

declare(strict_types=1);

namespace Webmozarts\Console\Parallelization\Process;

use DomainException;
use Generator;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Traversable;
use Webmozart\Assert\Assert;
use function func_get_args;
use function implode;

final class DummyProcess extends Process
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
        string $cwd = null,
        array $env = null,
        $input = null,
        ?float $timeout = 60
    ) {
        parent::__construct($command, $cwd, $env, $input, $timeout);

        $this->command = $command;
    }

    public function setTimeout(?float $timeout)
    {
        parent::setTimeout($timeout);

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function setInput($input)
    {
        Assert::isInstanceOf($input, InputStream::class);
        $this->input = $input;

        $this->calls[] = [__FUNCTION__];
    }

    public function setEnv(array $env)
    {
        parent::setEnv($env);

        $this->calls[] = [
            __FUNCTION__,
            func_get_args(),
        ];
    }

    public function start(callable $callback = null, array $env = [])
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

    /** @noinspection MagicMethodsValidityInspection */
    public function __destruct()
    {
    }

    public static function fromShellCommandline(
        string $command,
        string $cwd = null,
        array $env = null,
        $input = null,
        ?float $timeout = 60
    ) {
        return parent::fromShellCommandline(
            $command,
            $cwd,
            $env,
            $input,
            $timeout
        );
    }

    public function stop(float $timeout = 10, int $signal = null)
    {
        throw new DomainException('Unexpected call.');
    }

    public function __sleep()
    {
        throw new DomainException('Unexpected call.');
    }

    public function __wakeup()
    {
        throw new DomainException('Unexpected call.');
    }

    public function __clone()
    {
        throw new DomainException('Unexpected call.');
    }

    public function run(callable $callback = null, array $env = []): int
    {
        throw new DomainException('Unexpected call.');
    }

    public function mustRun(callable $callback = null, array $env = []): Process
    {
        throw new DomainException('Unexpected call.');
    }

    public function restart(callable $callback = null, array $env = []): Process
    {
        throw new DomainException('Unexpected call.');
    }

    public function wait(callable $callback = null)
    {
        throw new DomainException('Unexpected call.');
    }

    public function waitUntil(callable $callback): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public function getPid()
    {
        throw new DomainException('Unexpected call.');
    }

    public function signal(int $signal)
    {
        throw new DomainException('Unexpected call.');
    }

    public function disableOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function enableOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function isOutputDisabled()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIncrementalOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIterator(int $flags = 0)
    {
        throw new DomainException('Unexpected call.');
    }

    public function clearOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getErrorOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIncrementalErrorOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function clearErrorOutput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getExitCode()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getExitCodeText()
    {
        throw new DomainException('Unexpected call.');
    }

    public function isSuccessful()
    {
        throw new DomainException('Unexpected call.');
    }

    public function hasBeenSignaled()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getTermSignal()
    {
        throw new DomainException('Unexpected call.');
    }

    public function hasBeenStopped()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStopSignal()
    {
        throw new DomainException('Unexpected call.');
    }

    public function isStarted()
    {
        throw new DomainException('Unexpected call.');
    }

    public function isTerminated()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStatus()
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

    public function getTimeout()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getIdleTimeout()
    {
        throw new DomainException('Unexpected call.');
    }

    public function setIdleTimeout(?float $timeout)
    {
        throw new DomainException('Unexpected call.');
    }

    public function setTty(bool $tty)
    {
        throw new DomainException('Unexpected call.');
    }

    public function isTty()
    {
        throw new DomainException('Unexpected call.');
    }

    public function setPty(bool $bool)
    {
        throw new DomainException('Unexpected call.');
    }

    public function isPty()
    {
        throw new DomainException('Unexpected call.');
    }

    public function setWorkingDirectory(string $cwd)
    {
        throw new DomainException('Unexpected call.');
    }

    public function getInput()
    {
        throw new DomainException('Unexpected call.');
    }

    public function checkTimeout()
    {
        throw new DomainException('Unexpected call.');
    }

    public function getStartTime(): float
    {
        throw new DomainException('Unexpected call.');
    }

    public function setOptions(array $options)
    {
        throw new DomainException('Unexpected call.');
    }

    public static function isTtySupported(): bool
    {
        throw new DomainException('Unexpected call.');
    }

    public static function isPtySupported()
    {
        throw new DomainException('Unexpected call.');
    }

    protected function buildCallback(callable $callback = null)
    {
        throw new DomainException('Unexpected call.');
    }

    protected function updateStatus(bool $blocking)
    {
        throw new DomainException('Unexpected call.');
    }

    protected function isSigchildEnabled()
    {
        throw new DomainException('Unexpected call.');
    }
}
