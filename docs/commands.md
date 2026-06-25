# Maintenance commands

Two security-hardened Artisan commands for database and filesystem maintenance.
Both extend the laranail console base, register under the org-namespaced name
`laranail::toolkit.<command>` (with a short retained alias), and inject their
collaborators — no facades in the core logic.

| Command | Namespaced name | Alias |
|---|---|---|
| Database manager | `laranail::toolkit.database` | `laranail:database` |
| Tidy | `laranail::toolkit.tidy` | `tidy` |

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

---

## See also

- [make-crud](make-crud.md) — API CRUD generator.
- [Macros](macros.md) — includes the `ide-helper:macros` stub regenerator.

[← Docs index](../README.md#documentation)
