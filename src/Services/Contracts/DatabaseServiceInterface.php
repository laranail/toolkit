<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Database helpers: join detection, timestamp modification, session-scoped
 * view counting, morph aliases, relationship sync data, and maintenance
 * (cache/log/symlink) cleanup confined to the application directories.
 */
interface DatabaseServiceInterface
{
    /** Whether the given query already joins the named table. */
    public function isJoined(mixed $query, string $table): bool;

    /**
     * Set the given date columns on the model without touching `updated_at`.
     *
     * @param array<string, mixed> $dates Map of column => date value.
     */
    public function modifyTimestamps(array $dates, Model $model): bool;

    /**
     * Increment the model's `views` once per session.
     *
     * @return bool True when the view was counted, false when already viewed this session.
     */
    public function handleViewCount(Model $object, string $sessionName): bool;

    /**
     * Merge morph-class aliases into `app.aliases`.
     *
     * @param array<string, string> $aliases
     */
    public function setMorphClassNames(array $aliases): void;

    /**
     * Build pivot sync data keyed by id.
     *
     * @param string|list<string>  $ids
     * @param array<string, mixed> $data
     *
     * @return array<string, array<string, mixed>>
     */
    public function generateRelationshipSyncData(
        string|array $ids,
        array $data = [],
        string $columnName = 'id'
    ): array;

    /** Flush the application cache and remove framework/bootstrap cache files. */
    public function clearCache(): bool;

    /** Remove log/profiler files under storage (keeping `.gitignore`). */
    public function clearLogFiles(): bool;

    /** Remove the `public/storage` symlink when present. */
    public function deleteStorageSymlink(): bool;
}
