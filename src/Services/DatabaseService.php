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
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
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
        $sessionKey = $sessionName . '.' . $object->getKey();

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
            $id = trim($id);

            if ($id === '') {
                continue;
            }

            $out[$id] = Arr::where(array_unique(array_merge([
                $columnName => Str::uuid()->toString(),
            ], $data)), static fn ($value): bool => $value !== null);
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
