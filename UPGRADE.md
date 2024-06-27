## From 1.x to 2.x

- `Parallelization::fetchItems()` now requires `OutputInterface` as a second parameter
- A lot more type-safety and validation has been added with a comprehensive
  message upon failure.
- When no number of process is given (via `-p|--processes`), the command is no
  longer executed in the main process. Instead, the number of processes is guessed
  based on the maximum number of cores detected. Note that the execution within
  the main process instead of spawning child processes can be guaranteed by
  passing `--main-process`.
- Child processes will always be spawned regardless of the number of items
  (known or not), the number of processes defined (or not defined). Instead,
  the processing will occur only if the `--main-process` option is passed.
- `ParallelizationInput` should be created from the new factory `::fromInput()`
- `ContainerAwareCommand` has been removed. `Parallelization` instead provides a
  `::getContainer()` method which by defaults returns the Symfony Application
  Kernel's container when available.
- The `Parallelization#logError` property has been deprecated. Override the new
  `::createErrorHandler()` method instead.
- Rename `ParallelizationInput::configureParallelization()` to `::configureCommand()`
- The `Parallelization::configureParallelization()` method has been deprecated.
  Use `ParallelizationInput::configureCommand()` directly instead.
- Most of the execution of the `Parallelization` trait has been moved to the
  `ParallelExecutor`. Certain pieces are no longer overridable, but each key
  element should remain configurable. Since creating the `ParallelExecutor` is
  quite complex, a `ParallelExecutorFactory` has been introduced allowing to
  create an executor whilst overriding only the necessary bits. All the following
  methods have been deprecated in favour of overriding `::getParallelExecutableFactory()`
  to configure the factory instead:
    - `::getProgressSymbol()`
    - `::detectPhpExecutable()`
    - `::getWorkingDirectory()`
    - `::getEnvironmentVariables()`
    - `::getSegmentSize()`
    - `::getBatchSize()`
    - `::getConsolePath()`
- The following methods have been removed from `Parallelization` and have no
  replacement (the existing and new extension points should be enough to cover
  those):
   - `::executeMasterProcess()`
   - `::executeChildProcess()`
   - `::processChildOutput()`
   - `::runTolerantSingleCommand()`
   - `::serializeInputOptions()`
   - `::quoteOptionValue()`
   - `::isValueRequiresQuoting()`
- `::fetchItems()` can now return an `iterable` instead of `array`
- `::getItemName()` can now take `null` for an unknown number of items
- Ensures that if an item is given, then the processing is done in the main
  process. An item also cannot be passed to a child process (via the argument).


## New extension points

- A `ErrorHandler` interface has been introduced which offers an extension point
  on how to handle an error that occurred when processing an item. The
  `Parallelization` trait offers a `::createErrorHandler()` method which by default
  logs the error and resets the Container (when resettable) in order to avoid
  issues such as a broken Doctrine UnitOfWork.
- The logging has been consolidated into a `Logger` interface which can be
  overridden by `::createLogger()` provided in the `Parallelization` trait
- The logging has been consolidated into a `Logger` interface which can be
  overridden by `::createLogger()` provided in the `Parallelization` trait
- The process launching strategy and details has been moved to the `ProcessLauncher`
  and `ProcessLauncherFactory` interfaces. To change the process launching
  implementation used, you can configure it via `ParallelExecutorFactory::withProcessLauncherFactory()`.

Notable "internal" BC breaks:

- `ItemBatchIterable` has been renamed to `ChunkedItemsIterator`
- `ParallelizationInput` is now only validated when creating it via the new
  factory `::fromInput()`
- Most of the execution of the `Parallelization` trait has been moved to the
  `ParallelExecutor`. Certain pieces are no longer overridable, but each key
  element should remain configurable.
- `ProcessLauncher` has been renamed to `SymfonyProcessLauncher` and moved under
  the `Process` namespace.
- A few parameters have been added to `SymfonyProcessLauncher`
