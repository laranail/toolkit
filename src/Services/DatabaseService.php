<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;
use SplFileInfo;
use Throwable;

/**
 * Database helpers and maintenance utilities.
 *
 * File cleanup is confined to subdirectories of the injected application base
 * path: every candidate is resolved with realpath() and verified to sit inside
 * the expected directory before deletion, so a stray symlink cannot redirect a
 * delete outside the intended tree.
 */
final readonly class DatabaseService implements DatabaseServiceInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private Session $session,
        private string $basePath,
    ) {}

    /**
     * Whether a database connection can actually be opened. Resolves the PDO
     * inside a try/catch so a failure is a boolean, not an exception.
     */
    public function canConnect(?string $connection = null): bool
    {
        try {
            DB::connection($connection)->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Whether a connection can be opened from an ad-hoc config array, WITHOUT
     * touching the application's configured connections.
     *
     * Safe replacement for the dropped `setDatabaseCredentials`: it registers a
     * throwaway, uniquely-named connection, opens its PDO inside a try/catch, and
     * always purges + unsets the temp config afterwards. The default connection
     * is never mutated, and credentials are never logged.
     *
     * @param array<string, mixed> $config a `database.connections.*`-shaped array
     */
    public function canConnectWith(array $config): bool
    {
        $temp = 'laranail_probe_' . bin2hex(random_bytes(8));
        $key = "database.connections.$temp";

        config()->set($key, $config);

        try {
            DB::connection($temp)->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            DB::purge($temp);

            /** @var array<string, mixed> $connections */
            $connections = (array) config('database.connections', []);
            unset($connections[$temp]);
            config()->set('database.connections', $connections);
        }
    }

    /** Whether a table exists on the (optional) connection. Exception-safe. */
    public function tableExists(string $table, ?string $connection = null): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether a column exists on a table. Exception-safe. */
    public function columnExists(string $table, string $column, ?string $connection = null): bool
    {
        try {
            return Schema::connection($connection)->hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The names of all configured database connections.
     *
     * @return list<string>
     */
    public function connectionNames(): array
    {
        /** @var array<string, mixed> $connections */
        $connections = (array) config('database.connections', []);

        return array_values(array_filter(array_keys($connections), is_string(...)));
    }

    public function isJoined(mixed $query, string $table): bool
    {
        if ($query instanceof EloquentBuilder) {
            $query = $query->getQuery();
        }

        if (!$query instanceof QueryBuilder) {
            return false;
        }

        $joins = $query->joins;

        if ($joins === null) {
            return false;
        }

        foreach ($joins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }

    public function modifyTimestamps(array $dates, Model $model): bool
    {
        if ($dates === []) {
            return false;
        }

        try {
            $model->timestamps = false;

            foreach ($dates as $column => $date) {
                $model->setAttribute($column, $date);
            }

            $result = $model->save();

            if ($result) {
                $this->logger->info('Model timestamps modified', [
                    'model' => $model::class,
                    'id' => $model->getKey(),
                    'columns' => array_keys($dates),
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            $this->logger->error('Failed to modify timestamps', [
                'model' => $model::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function handleViewCount(Model $object, string $sessionName): bool
    {
        $sessionKey = $sessionName . '.' . Cast::toString($object->getKey());

        if ($this->session->has($sessionKey)) {
            return false;
        }

        try {
            $object->newQuery()->increment('views');
            $this->session->put($sessionKey, time());

            $this->logger->debug('View count incremented', [
                'model' => $object::class,
                'id' => $object->getKey(),
            ]);

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to increment view count', [
                'model' => $object::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function setMorphClassNames(array $aliases): void
    {
        $oldAliases = Config::get('app.aliases', []);
        Config::set(['app.aliases' => array_merge((array) $oldAliases, $aliases)]);

        $this->logger->info('Morph class aliases set', [
            'count' => count($aliases),
        ]);
    }

    public function generateRelationshipSyncData(
        string|array $ids,
        array $data = [],
        string $columnName = 'id'
    ): array {
        $ids = is_array($ids) ? $ids : [$ids];
        $out = [];

        foreach ($ids as $id) {
            $id = trim(Cast::toString($id));

            if ($id === '') {
                continue;
            }

            $merged = array_merge([
                $columnName => Str::uuid()->toString(),
            ], $data);

            // Drop null values, then de-duplicate the remaining scalar values.
            $filtered = Arr::where($merged, static fn (mixed $value): bool => $value !== null);

            $out[$id] = array_unique(array_map(
                static fn (mixed $value): string => Cast::toString($value),
                $filtered,
            ));
        }

        return $out;
    }

    public function clearCache(): bool
    {
        try {
            Event::dispatch('cache:clearing');
            Cache::flush();

            $cachePath = $this->confinedDirectory('storage/framework/cache');
            if ($cachePath !== null) {
                foreach (File::files($cachePath) as $file) {
                    if (!$this->isContainedFile($file, $cachePath)) {
                        continue;
                    }

                    if (preg_match('/facade-.*\.php$/', $file->getFilename()) === 1) {
                        File::delete($file->getPathname());
                    }
                }
            }

            $bootstrapPath = $this->confinedDirectory('bootstrap/cache');
            if ($bootstrapPath !== null) {
                foreach (File::allFiles($bootstrapPath) as $file) {
                    if (!$this->isContainedFile($file, $bootstrapPath)) {
                        continue;
                    }

                    if (str_ends_with($file->getFilename(), '.php')) {
                        File::delete($file->getPathname());
                    }
                }
            }

            Event::dispatch('cache:cleared');

            $this->logger->info('Cache cleared successfully');

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to clear cache', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function clearLogFiles(): bool
    {
        try {
            Event::dispatch('logs:clearing');

            foreach (['clockwork', 'debugbar', 'logs'] as $directory) {
                $path = $this->confinedDirectory('storage/' . $directory);

                if ($path === null) {
                    continue;
                }

                foreach (File::allFiles($path) as $file) {
                    if (!$this->isContainedFile($file, $path)) {
                        continue;
                    }

                    if (!str_ends_with($file->getFilename(), '.gitignore')) {
                        File::delete($file->getPathname());
                    }
                }
            }

            Event::dispatch('logs:cleared');

            $this->logger->info('Log files cleared successfully');

            return true;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to clear log files', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function deleteStorageSymlink(): bool
    {
        try {
            $publicStorage = $this->basePath . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage';

            if (File::exists($publicStorage)) {
                File::delete($publicStorage);

                $this->logger->info('Storage symlink deleted');

                return true;
            }

            return false;
        } catch (Throwable $exception) {
            $this->logger->error('Failed to delete storage symlink', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Resolve a directory relative to the application base path, returning its
     * real path only when it exists AND stays inside the base path. Returns null
     * otherwise (so callers skip the directory rather than deleting elsewhere).
     */
    private function confinedDirectory(string $relative): ?string
    {
        $base = realpath($this->basePath);

        if ($base === false) {
            return null;
        }

        $target = realpath($base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));

        if ($target === false || !is_dir($target)) {
            return null;
        }

        return $this->isWithin($target, $base) ? $target : null;
    }

    /**
     * Whether a discovered file's real path is contained within the given root.
     * Guards against symlinks pointing outside the swept directory.
     */
    private function isContainedFile(SplFileInfo $file, string $root): bool
    {
        $real = realpath($file->getPathname());

        if ($real === false || !is_file($real)) {
            return false;
        }

        return $this->isWithin($real, $root);
    }

    private function isWithin(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $path === rtrim($root, DIRECTORY_SEPARATOR) || str_starts_with($path, $root);
    }
}
