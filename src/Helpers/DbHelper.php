<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Safe, read-only database introspection helpers.
 *
 * The motivating "check the DB connection" case — done safely. Unlike the
 * legacy ValidationService credential check, nothing here mutates config() or
 * logs credentials (see docs/migration/RESTORE-CANDIDATES.md).
 */
final class DbHelper
{
    /**
     * Whether a database connection can actually be opened. Resolves the PDO
     * inside a try/catch so a failure is a boolean, not an exception.
     */
    public static function canConnect(?string $connection = null): bool
    {
        try {
            DB::connection($connection)->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether a table exists on the (optional) connection. Exception-safe. */
    public static function tableExists(string $table, ?string $connection = null): bool
    {
        try {
            return Schema::connection($connection)->hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    /** Whether a column exists on a table. Exception-safe. */
    public static function columnExists(string $table, string $column, ?string $connection = null): bool
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
    public static function connectionNames(): array
    {
        /** @var array<string, mixed> $connections */
        $connections = (array) config('database.connections', []);

        return array_values(array_filter(array_keys($connections), is_string(...)));
    }
}
