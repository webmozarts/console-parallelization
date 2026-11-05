# Contributing

Thanks for helping out! Bug reports, fixes, documentation improvements and
new features are all welcome.

By taking part in this project, you agree to follow its
[code of conduct](CODE_OF_CONDUCT.md).


## How the project is organized

Read the [README](README.md) first: it explains how the library works and what
its API looks like.

The core of the code is `ParallelExecutor`. Its `::execute()` method runs
either the main process or a child process, based on the `--child` option.
`Configuration` computes the number of segments and batches.

The rest of `src/` is split by concern:

- `Input/` reads the parallelization options and builds the command line of
  the child processes, forwarding the options the user passed.
- `Process/` starts the child processes and feeds them their items. It also
  finds the PHP executable and counts the CPU cores.
- `Logger/` writes the progress and the summary to the output.
- `ErrorHandler/` decides what happens when processing an item fails.

Classes and methods tagged `@internal` are not part of the public API, so you
can change them without a deprecation.


## Tests

The tests are split into three groups:

- Unit tests live in `tests/` and mirror the layout of `src/`. For example,
  `src/ChunkedItemsIterator.php` is covered by `tests/ChunkedItemsIteratorTest.php`.
- Integration tests live in `tests/Integration/`. They run real commands of a
  small Symfony application (`tests/Integration/App/`) that spawn actual child
  processes.
- The AutoReview tests in `tests/AutoReview/` check the project itself rather
  than the library. For example, `MakefileTest` checks the output of
  `make help`. If you add, rename or document a Make command, update
  `tests/AutoReview/makefile_help_output` to match.

The project requires a 100% mutation score: [Infection] fails if any mutant
survives. A mutant is a small change Infection makes to the source code, such
as turning `>` into `>=`. When one survives, add or tighten a test so it
fails on that mutant.

The CI also runs the tests against the lowest and highest supported versions
of each dependency, once for each supported Symfony version. The list of
Symfony versions lives in the `symfony-version` job of
[`.github/workflows/tests.yaml`](.github/workflows/tests.yaml).


## Tooling

We run the checks, such as the coding style, the tests and the static
analysis, with [Make]. Run `make help` to list the available commands.

If you want to work in an isolated environment, for example to let a coding
agent run commands, the project ships a [Docker Sandbox] setup. Run
`make sbx_create` to create one. Each directory under `.sbx/` is a kit that
adds to the sandbox, such as installing PHP or allowing a network domain.
`.sbx/project-local/` is gitignored, so you can put your own customization
there. A sandbox picks up a kit change only when you recreate it.


## Pull requests

We squash-merge pull requests, so the pull request title becomes the commit
message on `main`. Write it in the [Conventional Commits] style, the same as
the existing history, for example `fix: Keep returning a string from the
legacy detectPhpExecutable()`.

If your change breaks backward compatibility, mark it with a `!` after the
type, as in `feat!: Drop support for PHP 8.1 and 8.2`, and document the
migration path in [UPGRADE.md](UPGRADE.md).


[Conventional Commits]: https://www.conventionalcommits.org
[Docker Sandbox]: https://docs.docker.com/ai/sandboxes/
[Infection]: https://infection.github.io
[Make]: https://www.gnu.org/software/make/
