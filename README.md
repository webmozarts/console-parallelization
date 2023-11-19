# Parallelization for the Symfony Console

This library supports the parallelization of Symfony Console commands. 

- [How it works](#how-it-works)
- [Installation](#installation)
- [Usage](#usage)
- [The API](#the-api)
  - [Items](#items)
  - [Segments](#segments)
  - [Batches](#batches)
  - [Configuration](#configuration)
  - [Hooks](#hooks)
- [Contribute](#contribute)
- [Upgrade](#upgrade)
- [Authors](#authors)
- [License](#license)


## How it works

When you launch a command with multiprocessing enabled, a
main process fetches *items* and distributes them across the given number of
child processes over the standard input. Child processes are killed after a fixed number of items
(a *segment*) in order to prevent them from slowing down over time.

Optionally, the work of child processes can be split down into further chunks
(*batches*). You can perform certain work before and after each of these batches
(for example flushing changes to the database) in order to optimize the
performance of your command.


## Installation

Use [Composer] to install the package:

```
$ composer require webmozarts/console-parallelization
```


## Usage

Add add parallelization capabilities to your project, you can either extend the
`ParallelCommand` class or use the `Parallelization` trait:

```php
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webmozarts\Console\Parallelization\ParallelCommand;
use Webmozarts\Console\Parallelization\Parallelization;
use Webmozarts\Console\Parallelization\Input\ParallelizationInput;

class ImportMoviesCommand extends ParallelCommand
{
    public function __construct()
    {
        parent::__construct('import:movies');
    }

    protected function configure(): void
    {
        parent::configure();
        
        // ...
    }

    protected function fetchItems(InputInterface $input, OutputInterface $output): iterable
    {
        // open up the file and read movie data...

        // return items as strings
        return [
            '{"id": 1, "name": "Star Wars"}',
            '{"id": 2, "name": "Django Unchained"}',
            // ...
        ];
    }

    protected function runSingleCommand(string $item, InputInterface $input, OutputInterface $output): void
    {
        $movieData = json_decode($item);
   
        // insert into the database
    }

    protected function getItemName(?int $count): string
    {
        if (null === $count) {
            return 'movie(s)';
        }

        return 1 === $count ? 'movie' : 'movies';
    }
}
```

You can run this command like a regular Symfony Console command:

```
$ bin/console import:movies --main-process
Processing 2768 movies in segments of 2768, batches of 50, 1 round, 56 batches in 1 process

 2768/2768 [============================] 100% 56 secs/56 secs 32.0 MiB
            
Processed 2768 movies.
```

Or, if you want, you can run the command using parallelization:

```
$ bin/console import:movies
# or with a specific number of processes instead:
$ bin/console import:movies --processes 2
Processing 2768 movies in segments of 50, batches of 50, 56 rounds, 56 batches in 2 processes

 2768/2768 [============================] 100% 31 secs/31 secs 32.0 MiB
            
Processed 2768 movies.
```


## The API

### Items

The main process fetches all the items that need to be processed and passes
them to the child processes through their Standard Input (STDIN). Hence, items must
fulfill two requirements:

* Items must be strings
* Items must not contain newlines

Typically, you want to keep items small in order to offload processing from the
main process to the child process. Some typical examples for items:

* The main process reads a file and passes the lines to the child processes
* The main processes fetches IDs of database rows that need to be updated and passes them to the child processes


### Segments

When you run a command with multiprocessing enabled, the items returned by
`fetchItems()` are split into segments of a fixed size. Each child processes
process a single segment and kills itself after that.

By default, the segment size is the same as the batch size (see below), but you 
can try to tweak the performance of your command by choosing a different segment
size (ideally a multiple of the batch size). You can do so by overriding the 
`getSegmentSize()` method:

```php
protected function getSegmentSize(): int
{
    return 250;
}
```


### Batches

By default, the batch size and the segment size are the same. If desired, you can
however choose a smaller batch size than the segment size and run custom code
before or after each batch. You will typically do so in order to flush changes
to the database or free resources that you don't need anymore.

To run code before/after each batch, override the hooks `runBeforeBatch()` and
`runAfterBatch()`:

```php
protected function runBeforeBatch(InputInterface $input, OutputInterface $output, array $items): void
{
    // e.g. fetch needed resources collectively
}

protected function runAfterBatch(InputInterface $input, OutputInterface $output, array $items): void
{
    // e.g. flush database changes and free resources
}

protected function configureParallelExecutableFactory(
      ParallelExecutorFactory $parallelExecutorFactory,
      InputInterface $input,
      OutputInterface $output,
): ParallelExecutorFactory {
    return $parallelExecutorFactory
        ->withRunAfterBatch($this$this->runBeforeBatch(...))
        ->withRunAfterBatch($this$this->runAfterBatch(...));
}
```

You can customize the default batch size of 50 by overriding the `getBatchSize()`
method:

```php
protected function configureParallelExecutableFactory(
      ParallelExecutorFactory $parallelExecutorFactory,
      InputInterface $input,
      OutputInterface $output,
): ParallelExecutorFactory {
    return $parallelExecutorFactory
        ->withBatchSize(150);
}
```


### Configuration

The library offers a wide variety of configuration settings:

- `::getParallelExecutableFactory()` allows you to completely configure the
  `ParallelExecutorFactory` factory which goes from fragment, batch sizes, which
  PHP executable is used or any of the [process handling hooks](#hooks).
- `::configureParallelExecutableFactory()` is a different, lighter extension
  point to configure the `ParallelExecutorFactory` factory.
- `::getContainer()` allows you to configure which container is used. By default,
  it passes the application's kernel's container if there is one. This is used
  by the default error handler which resets the container in-between each item
  failure to avoid things such as a broken Doctrine entity manager.
  If you are not using a kernel (e.g. outside a Symfony application), no
  container will be returned by default.
- `::createErrorHandler()` allows you to configure the error handler you want to use. 
- `::createLogger()` allows you to completely configure the logger you want.


### Hooks

The library supports several process hooks which can be configured via
`::configureParallelExecutableFactory()`:

| Method                                    | Scope         | Description                                                                         |
|-------------------------------------------|---------------|-------------------------------------------------------------------------------------|
| `runBeforeFirstCommand($input, $output)`  | Main process  | Run before any child process is spawned                                             |
| `runAfterLastCommand($input, $output)`    | Main process  | Run after all child processes have completed                                        |
| `runBeforeBatch($input, $output, $items)` | Child process | Run before each batch in the child process (or main if no child process is spawned) |
| `runAfterBatch($input, $output, $items)`  | Child process | Run after each batch in the child process (or main if no child process is spawned)  |


## Contribute

Contributions to the package are always welcome!

* Report any bugs or issues you find on the [issue tracker].
* You can grab the source code at the package's [Git repository].

To run the CS fixer and tests you can use the command `make`. More details
available with `make help`.

## Upgrade

See the [upgrade guide](UPGRADE.md).


Authors
-------

* [Bernhard Schussek]
* [Théo Fidry]
* [The Community Contributors]


## License

All contents of this package are licensed under the [MIT license].


[Composer]: https://getcomposer.org
[Bernhard Schussek]: http://webmozarts.com
[Théo Fidry]: http://webmozarts.com
[The Community Contributors]: https://github.com/webmozarts/console-parallelization/graphs/contributors
[issue tracker]: https://github.com/webmozarts/console-parallelization/issues
[Git repository]: https://github.com/webmozarts/console-parallelization
[MIT license]: LICENSE.md_
