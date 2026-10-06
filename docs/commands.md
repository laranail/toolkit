# Artisan commands

Every Artisan command the toolkit ships extends the laranail console base
(`Simtabi\Laranail\Console\Tools\Commands\Command` from
[`laranail/console`](https://opensource.simtabi.com/console/) `^1.0`), registers
under the org-namespaced name `laranail::toolkit.<command>` (with a short retained
alias), and injects its collaborators — no facades in the core logic.

| Command | Namespaced name | Alias | Page |
|---|---|---|---|
| CRUD generator | `laranail::toolkit.make-crud` | `laranail::toolkit.make-crud` | [make-crud](make-crud.md) |
| IDE-helper macros | `laranail::toolkit.ide-helper-macros` | `laranail::toolkit.ide-helper-macros` | [macros](macros.md) |

---

## The console toolkit lifecycle

Extending the console base gives each command a managed lifecycle and a rich
output layer, exposed through two access points:

- **`$this->consoleWriter()`** — a fluent, immutable `ConsoleWriter` bound to the
  command's output. Beyond styling (`style`/`color`/`bold`/`underline`,
  `emoji`/`symbol`/`prefix`, `when`) it offers ready-to-use **context statuses**
  — `success()` / `error()` / `warning()` / `info()` / `note()` (`error` routes to
  stderr) — rendered as a coloured glyph + message, plus `line()` / `lines()` /
  `newLine()`.
- **`$this->services`** — a `CommandServiceManager` coordinating nine discrete
  services. The toolkit commands lean on:
  - **`performance()`** — execution-time + memory timing (`startTimer()` /
    `endTimer()`, `getFormattedExecutionTime()`).
  - **`signals()`** — graceful-shutdown signal handling. `setupSignalHandling()`
    traps `SIGTERM` / `SIGINT` via ext-pcntl (a **no-op on Windows / without
    pcntl**); a long loop polls `shouldKeepRunning()` (defaults `true`) and bails
    cleanly between units of work on Ctrl-C.
  - **`interaction()`** — confirmations via Laravel Prompts. `confirmAction()`
    drives a real prompt on a TTY and returns the **default in non-interactive
    mode** (so a piped/CI run never silently proceeds with a destructive action).
  - **`logger()`** — structured `logStart()` / `logCompletion()` records.
  - **`error()`** — structured exception capture that **auto-redacts** any
    context key matching `password` / `secret` / `token` / `key` /
    `authorization`, so credentials never reach a log channel.
  - **`metadata()`** — a per-run key/value bag (`add()` / `addMany()`) folded into
    the lifecycle log and any failure capture.
  - **`display()`** — `formatBytes()`, tables and progress bars.

The base wraps `run()` so `startCommand()` / `endCommand()` (timing + a
`logStart` / `logCompletion` pair) fire automatically, and any uncaught exception
is captured through the redacting `error()` service before the command exits
non-zero. The non-interactive flag is read from the input, so `--no-interaction`
(or a non-TTY) flips every `interaction()` call to its safe default.

### Per-command adoption

- **`make-crud`** (light) — writes all status output through `consoleWriter()`
  (`info` for progress, `success` for each generated file, `warning` for skips)
  and records the model/table/field count plus the list of **generated files** in
  `metadata()` for the completion log.
- **`ide-helper-macros`** (mid) — `consoleWriter()` output; wraps the
  reflection-heavy stub build in `performance()` timing; on success writes a
  structured `logger()->logCompletion()` carrying the documented-macro count and
  the stub size via `display()->formatBytes(strlen(...))`. Accepts `--path=` to
  override the output location (default
  `ide-helper/_ide_helper_macros.php` under the base path).

---

## Tidy moved to `laranail/artisan-ui`

The maintenance command `laranail::toolkit.tidy` was removed in 0.3.0. It now lives in
[`laranail/artisan-ui`](https://opensource.simtabi.com/documentation/laranail/artisan-ui/) as
`laranail::artisan-ui.tidy`, with the same actions, options and safety rules (storage-confined
deletion, scoped `storage` sweeps, `db` gated and kept out of `all`). Install that package and call
the new name:

```bash
php artisan laranail::artisan-ui.tidy logs --days=30 --force
```

---

## See also

- [make-crud](make-crud.md) — API CRUD generator.
- [Macros](macros.md) — includes the `laranail::toolkit.ide-helper-macros` stub regenerator.

[← Docs index](../README.md#documentation)
