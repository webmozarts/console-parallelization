## From 1.x to 2.x

- A lot more type-safety and validation has been added with a comprehensive
  message upon failure.
- `ContainerAwareCommand` has been removed. `Parallelization` instead provides a
  `::getContainer()` method which by defaults returns the Symfony Application
  Kernel's container when available.
- Most of the execution of the `Parallelization` trait has been moved to the
  `ParallelExecutor`. Certain pieces are no longer overridable, but each key
  element should remain configurable. Since creating the `ParallelExecutor` is
  quite complex, a `ParallelExecutorFactory` has been introduced allowing to
  create an executor whilst overriding only the necessary bits. The following
  methods no longer have any effect:
    - `::executeMasterProcess()`
    - `::executeChildProcess()`
    - `::processChildOutput()`
    - `::runTolerantSingleCommand()`
    - `::serializeInputOptions()`
    - `::quoteOptionValue()`
    - `::isValueRequiresQuoting()`
- A number of methods are no longer overrideable by default. Each option can
  still be configured, but via the `::getParallelExecutableFactory()` method instead.
  The affected methods are:
    - `::getProgressSymbol()`
    - `::detectPhpExecutable()`
    - `::getEnvironmentVariables()`
    - `::runBeforeFirstCommand()`
    - `::runAfterLastCommand()`
    - `::runBeforeBatch()`
    - `::runAfterBatch()`
    - `::getSegmentSize()`
    - `::getBatchSize()`
    - `::getConsolePath()`


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
