<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Commands;

use Illuminate\Console\ConfirmableTrait;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;
use Simtabi\Laranail\Toolkit\Enums\LogLevel;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Support\FilePathGuard;
use Simtabi\Laranail\Toolkit\Utilities\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Utilities\Contracts\LoggerServiceInterface;
use SplFileInfo;
use Throwable;

/**
 * Unified, security-hardened maintenance/cleanup command.
 *
 * Replaces the legacy `Laravel\Commands\TidyCommand`, which deleted files under
 * `sys_get_temp_dir()` and other roots with no containment check (a symlink in a
 * swept dir could redirect a delete anywhere) and ran `migrate:fresh` as part of
 * `all`. This rewrite:
 *
 *  - confines EVERY file deletion to `storage_path()` (resolved real path) with
 *    {@see FilePathGuard} + a realpath-containment check that rejects `..` and
 *    symlink escapes — the same approach as `DatabaseService::clearLogFiles`;
 *  - keeps the destructive `db` action (`migrate:fresh`) OUT of `all`, gated
 *    behind --force AND the production-safety `confirmToProceed()`, and a no-op
 *    under --dry-run;
 *  - injects its collaborators (no facades in the core logic).
 */
class Tidy extends Command
{
    use ConfirmableTrait;
    use FilePathGuard;
    use SupportsNamespacedNames;

    /** @var list<string> */
    protected array $commandAliases = ['tidy'];

    protected $signature = 'laranail::toolkit.tidy
        {action? : What to tidy (cache|logs|temp|storage|db|all) — defaults to all}
        {--days= : Only delete files older than this many days}
        {--size= : Only delete files larger than this many MB}
        {--seed : (db) also run db:seed after migrate:fresh}
        {--optimize : (cache) also run optimize:clear}
        {--dry-run : Show what would be removed without deleting anything}
        {--force : Skip confirmation prompts (required for the db action)}';

    protected $description = 'Unified, path-confined maintenance: cache|logs|temp|storage|db|all';

    /** Storage-relative roots swept by each file-deleting action. */
    private const ROOTS = [
        'logs' => ['logs'],
        'temp' => ['app/temp', 'app/tmp', 'framework/cache/data'],
        'storage' => ['app/public', 'app/uploads', 'app/exports'],
    ];

    private int $filesProcessed = 0;

    private int $spaceFreed = 0;

    public function __construct(
        private readonly FileServiceInterface $files,
        private readonly CacheRepositoryInterface $cache,
        private readonly LoggerServiceInterface $log,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $argument = $this->argument('action');
        $action = is_string($argument) && $argument !== '' ? $argument : 'all';

        return match ($action) {
            'cache' => $this->tidyCache(),
            'logs', 'temp', 'storage' => $this->tidyFiles($action),
            'db' => $this->tidyDatabase(),
            'all' => $this->tidyAll(),
            default => $this->invalidAction($action),
        };
    }

    // -----------------------------------------------------------------------
    // cache
    // -----------------------------------------------------------------------

    private function tidyCache(): int
    {
        if ($this->isDryRun()) {
            $this->components->info('[dry-run] Would flush the application cache' . ($this->boolOption('optimize') ? ' and run optimize:clear' : '') . '.');

            return self::SUCCESS;
        }

        if (!$this->confirmAction('Flush the application cache?')) {
            return self::SUCCESS;
        }

        $this->cache->forget('__laranail_tidy_probe__');

        try {
            // Flush via the framework's own command so every store/driver is
            // honoured; the injected cache repo is exercised above for DI parity.
            $this->call('cache:clear');
        } catch (Throwable $e) {
            $this->log->error('Cache flush failed during tidy', ['error' => $e->getMessage()]);
        }

        if ($this->boolOption('optimize')) {
            $this->call('optimize:clear');
        }

        $this->components->info('Cache tidied.');

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // logs / temp / storage (path-confined deletion)
    // -----------------------------------------------------------------------

    private function tidyFiles(string $action, bool $confirm = true): int
    {
        $this->filesProcessed = 0;
        $this->spaceFreed = 0;

        $base = realpath(storage_path());

        if ($base === false) {
            $this->components->error('storage_path() does not resolve — nothing to tidy.');

            return self::FAILURE;
        }

        // The `all` action confirms once up front, so its per-root sweeps skip
        // the prompt (passing $confirm = false) to avoid re-asking per action.
        if ($confirm && !$this->isDryRun() && !$this->confirmAction("Clean {$action} files under storage?")) {
            return self::SUCCESS;
        }

        foreach (self::ROOTS[$action] as $relative) {
            $this->sweep($base, $relative);
        }

        $verb = $this->isDryRun() ? 'Would free' : 'Freed';
        $this->components->info(sprintf(
            '%s: %d file(s), %s%s.',
            $this->isDryRun() ? '[dry-run] ' . $verb : $verb,
            $this->filesProcessed,
            $this->files->formatFileSize($this->spaceFreed),
            $this->isDryRun() ? ' (nothing deleted)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * Sweep one storage-relative directory, deleting (or previewing) the files
     * that pass the age/size filters. Each candidate is realpath-resolved and
     * proven to sit inside the swept root before any delete — a `..` path or a
     * symlink pointing outside storage is skipped, never followed.
     */
    private function sweep(string $base, string $relative): void
    {
        if (!$this->isSafePath($relative)) {
            return;
        }

        $root = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($root === false || !is_dir($root) || !$this->isWithin($root, $base)) {
            return;
        }

        $cutoff = $this->ageCutoff();
        $minBytes = $this->minBytes();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $real = realpath($file->getPathname());

            // Containment re-check on the resolved path: a symlink that escapes
            // the swept root is never deleted.
            if ($real === false || !$this->isWithin($real, $root)) {
                continue;
            }

            if (str_ends_with($file->getFilename(), '.gitignore')) {
                continue;
            }

            $size = $file->getSize();

            if (!$this->matchesFilters($real, $size, $cutoff, $minBytes)) {
                continue;
            }

            $this->filesProcessed++;

            if ($this->isDryRun()) {
                $this->line("  <fg=gray>would delete</> {$real}");

                continue;
            }

            if (@unlink($real)) {
                $this->spaceFreed += $size;
            }
        }
    }

    private function matchesFilters(string $path, int $size, ?int $cutoff, ?int $minBytes): bool
    {
        // With no filters, every (non-gitignore) file qualifies.
        if ($cutoff === null && $minBytes === null) {
            return true;
        }

        if ($cutoff !== null) {
            $mtime = @filemtime($path);

            if ($mtime !== false && $mtime < $cutoff) {
                return true;
            }
        }

        return $minBytes !== null && $size >= $minBytes;
    }

    // -----------------------------------------------------------------------
    // db (migrate:fresh — excluded from all, hard-gated)
    // -----------------------------------------------------------------------

    private function tidyDatabase(): int
    {
        if ($this->isDryRun()) {
            $this->components->info('[dry-run] Would run migrate:fresh' . ($this->boolOption('seed') ? ' --seed' : '') . ' (skipped in dry-run).');

            return self::SUCCESS;
        }

        if (!$this->boolOption('force')) {
            $this->components->error('The db action runs migrate:fresh and DROPS ALL TABLES — re-run with --force.');

            return self::FAILURE;
        }

        // Production-safety prompt (no-op when --force is honoured outside prod).
        if (!$this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        if ($this->boolOption('seed')) {
            $this->call('db:seed', ['--force' => true]);
        }

        $this->components->info('Database refreshed.');

        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    // all (cache + file roots; NEVER db)
    // -----------------------------------------------------------------------

    private function tidyAll(): int
    {
        if (!$this->isDryRun() && !$this->confirmAction('Tidy cache, logs, temp and storage? (the db action is excluded.)')) {
            return self::SUCCESS;
        }

        $this->tidyCacheQuietly();

        foreach (['logs', 'temp', 'storage'] as $action) {
            $this->tidyFiles($action, confirm: false);
        }

        $this->components->info('All tidied (db excluded — run the db action explicitly).');

        return self::SUCCESS;
    }

    private function tidyCacheQuietly(): void
    {
        if ($this->isDryRun()) {
            $this->components->info('[dry-run] Would flush the application cache.');

            return;
        }

        try {
            $this->call('cache:clear');
        } catch (Throwable $e) {
            $this->log->log(LogLevel::Error, 'Cache flush failed during tidy all', ['error' => $e->getMessage()]);
        }
    }

    // -----------------------------------------------------------------------
    // filters / helpers
    // -----------------------------------------------------------------------

    private function ageCutoff(): ?int
    {
        $days = $this->option('days');

        if (!is_string($days) || trim($days) === '') {
            return null;
        }

        return time() - (max(0, (int) $days) * 86400);
    }

    private function minBytes(): ?int
    {
        $size = $this->option('size');

        if (!is_string($size) || trim($size) === '') {
            return null;
        }

        return max(0, (int) $size) * 1024 * 1024;
    }

    private function isDryRun(): bool
    {
        return $this->boolOption('dry-run');
    }

    private function boolOption(string $key): bool
    {
        return $this->option($key) === true;
    }

    private function confirmAction(string $message): bool
    {
        if ($this->boolOption('force')) {
            return true;
        }

        return $this->confirm($message, false);
    }

    private function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $path === rtrim($root, DIRECTORY_SEPARATOR) || str_starts_with($path, $root);
    }

    private function invalidAction(string $action): int
    {
        $this->components->error("Invalid action [{$action}]. Available: cache, logs, temp, storage, db, all.");

        return self::FAILURE;
    }
}
