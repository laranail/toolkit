<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Helpers\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Throwable;

/**
 * Safe, read-only database introspection helpers.
 *
 * Nothing here mutates config() or logs credentials. Folded into
 * {@see Helper} — call via the `Helper::`
 * facade, never the trait directly.
 */
trait InteractsWithDatabase
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
    public static function canConnectWith(array $config): bool
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
