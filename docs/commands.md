# Artisan commands

Every Artisan command the toolkit ships extends the laranail console base
(`Simtabi\Laranail\Console\Tools\Commands\Command` from
[`laranail/console`](https://opensource.simtabi.com/console/) `^1.0`), registers
under the org-namespaced name `laranail::toolkit.<command>` (with a short retained
alias), and injects its collaborators — no facades in the core logic.

| Command | Namespaced name | Alias | Page |
|---|---|---|---|
| CRUD generator | `laranail::toolkit.make-crud` | `make:crud` | [make-crud](make-crud.md) |
| IDE-helper macros | `laranail::toolkit.ide-helper-macros` | `ide-helper:macros` | [macros](macros.md) |
| Tidy | `laranail::toolkit.tidy` | `tidy` | below |

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
- **`tidy`** (heavy) — `consoleWriter()` throughout; the file sweep is
  **signal-safe** (`shouldKeepRunning()` is polled per root and per file);
  destructive prompts route through `interaction()->confirmAction()` (the existing
  `db` gating is preserved); each run is timed with `performance()` and logged via
  `logger()->logCompletion()` with files-processed + space-freed `metadata()`.

---

## `laranail::toolkit.tidy` — maintenance / cleanup

Unified, path-confined cleanup: `cache`, `logs`, `temp`, `storage`, `db`, `all`.

```bash
php artisan laranail::toolkit.tidy [action] [options]
```

| Option | Description |
|---|---|
| `action` (argument) | `cache`, `logs`, `temp`, `storage`, `db`, or `all` (default). |
| `--days=` | Only delete files older than this many days. |
| `--size=` | Only delete files larger than this many MB. |
| `--seed` | (db) also run `db:seed` after `migrate:fresh`. |
| `--optimize` | (cache) also run `optimize:clear`. |
| `--dry-run` | Show what would be removed without deleting anything. |
| `--force` | Skip confirmation prompts (required for the `db` action). |

### Actions

- **`cache`** — flushes the application cache; with `--optimize` also runs
  `optimize:clear`. `--dry-run` previews; the freed space is reported.
- **`logs` / `temp` / `storage`** — delete files under the relevant
  `storage_path()` roots, filtered by `--days` (age) and/or `--size`.
  `.gitignore` files are always preserved.
- **`db`** — runs `migrate:fresh` (optionally `--seed`). **Excluded from
  `all`.** Requires `--force` AND passes the production-safety
  `confirmToProceed()` guard; it is a no-op under `--dry-run`.
- **`all`** — tidies cache + the `logs` / `temp` / `storage` roots. It **never**
  runs the destructive `db` action.

### Safety notes

- **Every deletion is confined to `storage_path()`.** The swept roots are
  storage-relative; each is realpath-resolved and proven to sit inside
  `realpath(storage_path())` before any delete. Each candidate file is
  realpath-resolved and re-checked for containment, so a `..` path or a symlink
  pointing **outside** storage is skipped, never followed. Paths are additionally
  screened by the `FilePathGuard` (`..` / null-byte rejection).
- **`--dry-run` deletes nothing** — it previews each candidate and reports the
  space that *would* be freed.
- **`db` is hard-gated** behind `--force` + `confirmToProceed()` and excluded
  from `all`, so a bulk tidy can never drop your tables.
- **Signal-safe sweep.** The deletion loop polls
  `signals()->shouldKeepRunning()` per root and per file, so a `SIGTERM` /
  `SIGINT` stops the sweep cleanly mid-directory rather than leaving it half-run.
  Without ext-pcntl (e.g. Windows) the check defaults `true`, so a normal run is
  unaffected. Every run is timed and a `logger()->logCompletion()` summary records
  files-processed and space-freed.

---

## See also

- [make-crud](make-crud.md) — API CRUD generator.
- [Macros](macros.md) — includes the `ide-helper:macros` stub regenerator.

[← Docs index](../README.md#documentation)
