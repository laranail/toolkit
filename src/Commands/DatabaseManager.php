<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Schema;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ImportDatabaseServiceInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Consolidated, security-hardened database management command.
 *
 * Replaces the legacy `Laravel\Commands\DatabaseCommand`, whose every operation
 * shelled out a `mysqldump`/`mysql` STRING through `Process::fromShellCommandline`
 * with the password interpolated into the command line (visible in `ps`, log
 * files, shell history) and the table name string-interpolated into a raw
 * `TRUNCATE`. This rewrite removes all of that:
 *
 *  - `import` / `restore` delegate to the transactional, path-guarded
 *    {@see ImportDatabaseServiceInterface} — no shell at all.
 *  - `clean` validates EACH table via `Schema::hasTable()` and quotes the
 *    identifier through the connection's query grammar (`wrapTable()`); it never
 *    string-interpolates an identifier into SQL.
 *  - `export` (mysql/mariadb only) runs `mysqldump` via {@see Process} built from
 *    an ARRAY of arguments — never a shell string — and passes the password to
 *    the child via a chmod-600 `--defaults-extra-file` (preferred) or the
 *    `MYSQL_PWD` env var, so it never appears in argv, `ps`, or any log line.
 *
 * No `exec`/`shell_exec`/backticks/`Process::fromShellCommandline` anywhere.
 */
class DatabaseManager extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['laranail:database'];

    protected $signature = 'laranail::toolkit.database
        {action : Action to perform (import|clean|restore|export)}
        {--file= : SQL file path for import/export/restore operations}
        {--connection= : Database connection to use (defaults to the app default)}
        {--tables= : Comma-separated list of tables (for the clean action)}
        {--force : Skip confirmation prompts}
        {--backup : Create an export backup before a destructive action}
        {--dry-run : Show what would happen without changing anything}';

    protected $description = 'Consolidated, security-hardened database management (import|clean|restore|export)';

    public function __construct(
        private readonly ImportDatabaseServiceInterface $importer,
        private readonly ConnectionResolverInterface $resolver,
        private readonly FileServiceInterface $files,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $action = is_string($action) ? $action : '';

        // Wire SIGTERM/SIGINT graceful-stop handlers (no-op without ext-pcntl, e.g.
        // on Windows) so the clean loop can bail between truncates on Ctrl-C.
        $this->services->signals()->setupSignalHandling();

        $this->services->metadata()->addMany([
            'action' => $action,
            'connection' => $this->connectionLabel(),
            'dry_run' => $this->isDryRun(),
        ]);

        return match ($action) {
            'import' => $this->handleImport(),
            'restore' => $this->handleRestore(),
            'clean' => $this->handleClean(),
            'export' => $this->handleExport(),
            default => $this->invalidAction($action),
        };
    }

    // -----------------------------------------------------------------------
    // import / restore (delegated — no shell)
    // -----------------------------------------------------------------------

    private function handleImport(): int
    {
        return $this->runImport('Import', 'This will execute the SQL file against the database.');
    }

    /**
     * `restore` is a backup-aware import: it always offers to snapshot the
     * current state first (forced on when --backup is set), then imports.
     */
    private function handleRestore(): int
    {
        if ($this->boolOption('backup') && !$this->backupBeforeDestructive()) {
            return self::FAILURE;
        }

        return $this->runImport('Restore', 'This will REPLACE current data with the contents of the SQL file.');
    }

    private function runImport(string $label, string $warning): int
    {
        $writer = $this->consoleWriter();
        $file = $this->fileOption();

        if ($file === null) {
            $writer->error('A --file=<path.sql> is required for this action.');

            return self::FAILURE;
        }

        if (!$this->files->exists($file)) {
            $writer->error("SQL file not found or unsafe path: [{$file}].");

            return self::FAILURE;
        }

        if ($this->isDryRun()) {
            $writer->info("[dry-run] Would {$label} <fg=cyan>{$file}</> into connection <fg=cyan>{$this->connectionLabel()}</>.");

            return self::SUCCESS;
        }

        if (!$this->confirmDestructive("{$label} database from [{$file}]? {$warning}")) {
            return self::SUCCESS;
        }

        try {
            $count = $this->importer->import($file, $this->connectionName());
        } catch (Throwable $e) {
            // Structured, auto-redacting capture: the connection config (and any
            // credentials inside it) never reaches the log — the error service
            // scrubs password/secret/token/key keys.
            $this->services->error()->logError($e, [
                'action' => strtolower($label),
                'connection' => $this->connectionLabel(),
                'file' => $file,
            ]);
            $writer->error("{$label} failed: " . $e->getMessage());

            return self::FAILURE;
        }

        $this->services->metadata()->add('statements', $count);
        $writer->success("{$label} complete: executed <fg=cyan>{$count}</> statement(s) on <fg=cyan>{$this->connectionLabel()}</>.");

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // clean (Schema-validated + grammar-quoted TRUNCATE)
    // -----------------------------------------------------------------------

    private function handleClean(): int
    {
        $writer = $this->consoleWriter();
        $connection = $this->connection();
        $tables = $this->resolveTablesToClean($connection);

        if ($tables === []) {
            $writer->error('No existing tables resolved to clean. Pass --tables=a,b or ensure the connection has tables.');

            return self::FAILURE;
        }

        if ($this->isDryRun()) {
            $writer->info('[dry-run] Would TRUNCATE: <fg=cyan>' . implode('</>, <fg=cyan>', $tables) . '</>.');

            return self::SUCCESS;
        }

        if ($this->boolOption('backup') && !$this->backupBeforeDestructive()) {
            return self::FAILURE;
        }

        if (!$this->confirmDestructive('Truncate ' . count($tables) . ' table(s): ' . implode(', ', $tables) . '? This DELETES ALL DATA in them.')) {
            return self::SUCCESS;
        }

        $signals = $this->services->signals();
        $cleaned = 0;

        foreach ($tables as $table) {
            // Bail out gracefully between truncates if a SIGTERM/SIGINT arrived.
            // shouldKeepRunning() defaults true (and stays true without pcntl),
            // so this never blocks a normal run.
            if (!$signals->shouldKeepRunning()) {
                $writer->warning('Termination requested — stopping after ' . $cleaned . ' table(s).');
                break;
            }

            // The query builder compiles a grammar-quoted, driver-correct
            // truncate (a real `TRUNCATE` on MySQL/Postgres, `DELETE` + sequence
            // reset on SQLite). The identifier is wrapped by the connection's
            // grammar — never string-interpolated — so a crafted name cannot
            // break out of the identifier context, and the name was already
            // proven to exist via Schema::hasTable, so only real tables reach
            // here. This is why no raw `TRUNCATE TABLE "$table"` SQL is built.
            $connection->table($table)->truncate();
            $cleaned++;
            $this->components->task("Truncated {$table}");
        }

        $this->services->metadata()->add('truncated', $cleaned);
        $writer->success('Cleaned <fg=cyan>' . $cleaned . '</> table(s).');

        return self::SUCCESS;
    }

    /**
     * Resolve the target tables, keeping only those that actually exist on the
     * connection (validated via Schema, so an injected name is rejected here).
     *
     * @return list<string>
     */
    private function resolveTablesToClean(ConnectionInterface $connection): array
    {
        $name = $this->connectionName();
        $requested = $this->tablesOption();

        if ($requested === []) {
            // No explicit list: fall back to every table on the connection.
            $requested = $this->allTableNames($connection);
        }

        $valid = [];

        foreach ($requested as $table) {
            if (Schema::connection($name)->hasTable($table)) {
                $valid[] = $table;

                continue;
            }

            $this->consoleWriter()->warning("Skipping unknown table [{$table}] (not found on connection).");
        }

        return array_values(array_unique($valid));
    }

    /**
     * Every table name on the connection, via the schema builder (never raw SQL).
     *
     * @return list<string>
     */
    private function allTableNames(ConnectionInterface $connection): array
    {
        $names = [];

        foreach ($connection->getSchemaBuilder()->getTables() as $table) {
            if (is_array($table) && isset($table['name']) && is_string($table['name'])) {
                $names[] = $table['name'];
            }
        }

        return $names;
    }

    // -----------------------------------------------------------------------
    // export (driver-aware, shell-safe)
    // -----------------------------------------------------------------------

    private function handleExport(): int
    {
        $writer = $this->consoleWriter();
        $connection = $this->connection();
        $driver = $connection->getDriverName();

        if (!in_array($driver, ['mysql', 'mariadb'], true)) {
            $writer->warning("Native export is mysql/mariadb only (this connection is [{$driver}]).");
            $writer->line('  Install <fg=yellow>spatie/db-dumper</> for portable, multi-driver dumps, or use that driver\'s own tooling.');

            return self::FAILURE;
        }

        $target = $this->exportTargetPath($connection);

        if ($this->isDryRun()) {
            $writer->info("[dry-run] Would export <fg=cyan>{$this->connectionLabel()}</> to <fg=cyan>{$target}</> via mysqldump.");

            return self::SUCCESS;
        }

        try {
            $this->runMysqlDump($connection, $target);
        } catch (Throwable $e) {
            // Auto-redacting capture: connection name only; credentials never
            // appear in argv or context, so nothing sensitive is logged.
            $this->services->error()->logError($e, [
                'action' => 'export',
                'connection' => $this->connectionLabel(),
                'target' => $target,
            ]);
            $writer->error('Export failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $size = $this->files->formatFileSize($this->files->size($target));
        $this->services->metadata()->addMany(['target' => $target, 'size' => $size]);
        $writer->success("Exported to <fg=cyan>{$target}</> ({$size}).");

        return self::SUCCESS;
    }

    /**
     * Build and run mysqldump from an ARRAY of arguments (never a shell string),
     * passing credentials to the child through a chmod-600 defaults-extra-file
     * (preferred) or the MYSQL_PWD env var — never on the command line.
     */
    private function runMysqlDump(ConnectionInterface $connection, string $target): void
    {
        $config = $this->connectionConfig($connection);

        $binary = new ExecutableFinder()->find('mysqldump');

        if ($binary === null) {
            throw new RuntimeException('The "mysqldump" binary was not found on the PATH.');
        }

        $host = $this->stringConfig($config, 'host', '127.0.0.1');
        $port = $this->stringConfig($config, 'port', '3306');
        $user = $this->stringConfig($config, 'username', '');
        $database = $this->stringConfig($config, 'database', '');
        $password = $this->stringConfig($config, 'password', '');

        if ($database === '') {
            throw new RuntimeException('No database name is configured for connection [' . $connection->getName() . '].');
        }

        $defaultsFile = $this->writeDefaultsFile($user, $password);

        try {
            // Argument ARRAY — each value is a discrete element, so nothing is
            // re-parsed by a shell and no value can inject a flag or command.
            $command = [$binary];

            if ($defaultsFile !== null) {
                // Must be the FIRST argument for mysqldump to honour it.
                $command[] = '--defaults-extra-file=' . $defaultsFile;
            } else {
                $command[] = '--user=' . $user;
            }

            $command[] = '--host=' . $host;
            $command[] = '--port=' . $port;
            $command[] = '--single-transaction';
            $command[] = '--routines';
            $command[] = '--triggers';
            $command[] = $database;

            // Password via env (consumed by mysqldump) only when we could not
            // write a defaults file; it is scoped to the child process and never
            // reaches argv/ps. The dump is streamed to the target via setOutput.
            $env = $defaultsFile === null && $password !== '' ? ['MYSQL_PWD' => $password] : [];

            $handle = fopen($target, 'wb');

            if ($handle === false) {
                throw new RuntimeException("Unable to open export target [{$target}] for writing.");
            }

            try {
                $process = new Process($command, base_path(), $env === [] ? null : $env, null, 600.0);
                $process->run(function (string $type, string $buffer) use ($handle): void {
                    if ($type === Process::OUT) {
                        fwrite($handle, $buffer);
                    }
                });
            } finally {
                fclose($handle);
            }

            if (!$process->isSuccessful()) {
                // Surface a sanitized failure — the error output is logged WITHOUT
                // any credentials (they were never on the command line).
                $this->logger->error('mysqldump failed', [
                    'connection' => $connection->getName(),
                    'exit_code' => $process->getExitCode(),
                ]);

                @unlink($target);

                throw new ProcessFailedException($process);
            }
        } finally {
            if ($defaultsFile !== null && is_file($defaultsFile)) {
                @unlink($defaultsFile);
            }
        }
    }

    /**
     * Write a chmod-600 my.cnf-style defaults file carrying the credentials, so
     * they are read by mysqldump from a private file instead of the command line.
     * Returns null when a temp file cannot be created (caller falls back to env).
     */
    private function writeDefaultsFile(string $user, string $password): ?string
    {
        $path = tempnam(sys_get_temp_dir(), 'laranail-mysql-');

        if ($path === false) {
            return null;
        }

        // Restrict to the owner BEFORE writing secrets.
        @chmod($path, 0600);

        $contents = "[client]\nuser=\"" . addslashes($user) . "\"\npassword=\"" . addslashes($password) . "\"\n";

        if (file_put_contents($path, $contents) === false) {
            @unlink($path);

            return null;
        }

        return $path;
    }

    private function exportTargetPath(ConnectionInterface $connection): string
    {
        $file = $this->fileOption();

        if ($file !== null) {
            return $file;
        }

        $database = $this->stringConfig($this->connectionConfig($connection), 'database', 'database');

        return base_path('database_export_' . $this->files->sanitizeFilename($database) . '_' . date('Y_m_d_His') . '.sql');
    }

    // -----------------------------------------------------------------------
    // backup helper
    // -----------------------------------------------------------------------

    private function backupBeforeDestructive(): bool
    {
        $connection = $this->connection();

        if (!in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->consoleWriter()->warning('Skipping --backup: native backup is mysql/mariadb only.');

            return true;
        }

        $target = base_path('database_backup_' . date('Y_m_d_His') . '.sql');

        try {
            $this->runMysqlDump($connection, $target);
        } catch (Throwable $e) {
            $this->services->error()->logError($e, [
                'action' => 'backup',
                'connection' => $this->connectionLabel(),
                'target' => $target,
            ]);
            $this->consoleWriter()->error('Backup failed, aborting: ' . $e->getMessage());

            return false;
        }

        $this->services->metadata()->add('backup', $target);
        $this->consoleWriter()->success("Backup written to <fg=cyan>{$target}</>.");

        return true;
    }

    // -----------------------------------------------------------------------
    // option / connection helpers
    // -----------------------------------------------------------------------

    private function connection(): ConnectionInterface
    {
        return $this->resolver->connection($this->connectionName());
    }

    private function connectionName(): ?string
    {
        $value = $this->option('connection');

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function connectionLabel(): string
    {
        return $this->connectionName() ?? 'default';
    }

    private function fileOption(): ?string
    {
        $value = $this->option('file');

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function tablesOption(): array
    {
        $value = $this->option('tables');

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn (string $t): bool => $t !== ''));
    }

    private function isDryRun(): bool
    {
        return $this->boolOption('dry-run');
    }

    private function boolOption(string $key): bool
    {
        return $this->option($key) === true;
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(ConnectionInterface $connection): array
    {
        $config = config('database.connections.' . $connection->getName(), []);

        if (!is_array($config)) {
            return [];
        }

        $typed = [];

        foreach ($config as $key => $value) {
            if (is_string($key)) {
                $typed[$key] = $value;
            }
        }

        return $typed;
    }

    private function confirmDestructive(string $message): bool
    {
        if ($this->boolOption('force')) {
            return true;
        }

        // The interaction service drives Laravel Prompts when a TTY is present and
        // returns the default (false) in non-interactive mode — so a piped/CI run
        // never silently proceeds with a destructive action.
        return $this->services->interaction()->confirmAction($message, false);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function stringConfig(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return $default;
    }

    private function invalidAction(string $action): int
    {
        $this->consoleWriter()->error("Invalid action [{$action}]. Available: import, clean, restore, export.");

        return self::FAILURE;
    }
}
