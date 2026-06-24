<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException;

/**
 * Generic, safe importer for a raw SQL dump into a database connection.
 *
 * The source path is validated (no `..` traversal / null bytes, must be an
 * existing readable `.sql` file) before any statement runs, statements are
 * executed inside a transaction on the chosen connection, and nothing about the
 * connection credentials is ever logged. Replaces the legacy
 * `Laravel\Services\ImportDatabaseService` (which delegated to a now-dropped
 * static `DatabaseHelper::restoreFromPath`).
 */
interface ImportDatabaseServiceInterface
{
    /**
     * Import the SQL file at $path into the given (or default) connection.
     *
     * @param string|null $connection Target connection name, or null for the default.
     *
     * @throws InvalidPathException When the path is unsafe, missing, unreadable or not a `.sql` file.
     * @throws \RuntimeException    When the import fails (the transaction is rolled back first).
     *
     * @return int The number of SQL statements executed.
     */
    public function import(string $path, ?string $connection = null): int;
}
