# Artisan commands

Every Artisan command the toolkit ships extends the laranail console base
(`Simtabi\Laranail\Console\Tools\Commands\Command` from
[`laranail/console`](https://opensource.simtabi.com/console/) `^2.5.0`), registers
under the org-namespaced name `laranail::toolkit.<command>` (with a short retained
alias), and injects its collaborators — no facades in the core logic.

| Command | Namespaced name | Alias | Page |
|---|---|---|---|
| CRUD generator | `laranail::toolkit.make-crud` | `make:crud` | [make-crud](make-crud.md) |
| IDE-helper macros | `laranail::toolkit.ide-helper-macros` | `ide-helper:macros` | [macros](macros.md) |
| Database manager | `laranail::toolkit.database` | `laranail:database` | below |
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
  the stub size via `display()->formatBytes(strlen(...))`.
- **`database`** (heavy) — `consoleWriter()` throughout; `signals()` make the
  `clean` truncate loop interruptible; every confirmation goes through
  `interaction()->confirmAction()`; failures are captured with
  `error()->logError()` (credentials stay redacted); each action records its
  parameters + result in `metadata()`.
- **`tidy`** (heavy) — `consoleWriter()` throughout; the file sweep is
  **signal-safe** (`shouldKeepRunning()` is polled per root and per file);
  destructive prompts route through `interaction()->confirmAction()` (the existing
  `db` gating is preserved); each run is timed with `performance()` and logged via
  `logger()->logCompletion()` with files-processed + space-freed `metadata()`.

---

## `laranail::toolkit.database` — database manager

Consolidated `import` / `clean` / `restore` / `export` for a connection.

```bash
php artisan laranail::toolkit.database <action> [options]
```

| Option | Description |
|---|---|
| `action` (argument) | `import`, `clean`, `restore`, or `export`. |
| `--file=` | SQL file path (import / restore; export target). |
| `--connection=` | Connection name (defaults to the app default). |
| `--tables=` | Comma-separated tables for `clean`. |
| `--force` | Skip confirmation prompts. |
| `--backup` | Run an export backup before a destructive action. |
| `--dry-run` | Show what would happen without changing anything. |

### Actions

- **`import`** — delegates to the transactional, path-guarded
  `ImportDatabaseServiceInterface`. The `.sql` file is validated (no `..` /
  null-byte path, must be a readable `.sql`) and every statement runs inside one
  transaction that rolls back on any failure.
- **`restore`** — a backup-aware import: with `--backup` it exports the current
  state first, then imports (REPLACE semantics).
- **`clean`** — truncates the given `--tables` (or every table on the
  connection). Each name is validated via `Schema::hasTable()`; the truncate is
  compiled by the query builder (`$connection->table($table)->truncate()`), so
  the identifier is grammar-quoted and driver-correct — never string-interpolated
  into raw SQL. Requires `--force` (or an interactive confirm) and honours
  `--dry-run`.
- **`export`** — **mysql / mariadb only.** Runs `mysqldump` via Symfony Process.
  For other drivers it prints a clear message suggesting `spatie/db-dumper`
  (a soft `suggest` dependency, guarded by `class_exists` — never hard-required).

### Safety notes

- **No shell strings.** `export` builds `mysqldump` from an **array of
  arguments** passed to `Symfony\Component\Process\Process` — host, port, user
  and database are discrete elements, so nothing is re-parsed by a shell and no
  value can inject a flag or command. There is no `shell_exec`, `exec`,
  backtick, `proc_open`, or `Process::fromShellCommandline` anywhere.
- **Credentials never hit the command line.** The DB password is passed to
  `mysqldump` through a chmod-600 `--defaults-extra-file` (preferred) or the
  `MYSQL_PWD` environment variable scoped to the child process — never as an
  argv element, so it never appears in `ps` or any log line. On failure only the
  connection name and exit code are logged, never credentials.
- **SQL-injection-safe `clean`.** A crafted `--tables` value (e.g.
  `legit; DROP TABLE legit;--`) is rejected by `Schema::hasTable()` (it is not a
  real table) and skipped — it never reaches a statement.
- **Interruptible & auto-redacting.** The `clean` truncate loop polls
  `signals()->shouldKeepRunning()` and stops cleanly between tables on
  `SIGTERM` / `SIGINT`. On failure the exception is logged through
  `services->error()->logError()`, which redacts any `password` / `secret` /
  `token` / `key` context key — so even if the connection config were attached, no
  credential would reach the log.

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
  pointing **outside** storage is skipped, never followed — the same containment
  approach as `DatabaseService::clearLogFiles`. Paths are additionally screened
  by the `FilePathGuard` (`..` / null-byte rejection).
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
