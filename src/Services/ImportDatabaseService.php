<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Database\ConnectionResolverInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException;
use Simtabi\Laranail\Toolkit\Services\Contracts\ImportDatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Support\FilePathGuard;
use Throwable;

/**
 * Generic, safe importer for a raw SQL dump into a database connection.
 *
 * Security posture:
 *  - The source path is validated via {@see FilePathGuard} (no `..` / null
 *    bytes), must be an existing readable regular file with a `.sql` extension.
 *  - Statements run inside a single transaction; any failure rolls the whole
 *    import back before re-throwing, so a partial import never lands.
 *  - Nothing about the connection credentials is ever logged — only the
 *    connection NAME and the statement count.
 *
 * Replaces the legacy `Laravel\Services\ImportDatabaseService`, which delegated
 * to a now-dropped static `DatabaseHelper::restoreFromPath`.
 */
final readonly class ImportDatabaseService implements ImportDatabaseServiceInterface
{
    use FilePathGuard;

    public function __construct(
        private ConnectionResolverInterface $resolver,
        private LoggerInterface $logger,
    ) {}

    public function import(string $path, ?string $connection = null): int
    {
        $real = $this->validatePath($path);

        $sql = file_get_contents($real);

        if ($sql === false) {
            throw InvalidPathException::create($path, 'Unable to read SQL file');
        }

        $statements = $this->splitStatements($sql);

        if ($statements === []) {
            return 0;
        }

        $db = $this->resolver->connection($connection);

        try {
            $db->beginTransaction();

            foreach ($statements as $statement) {
                $db->statement($statement);
            }

            $db->commit();
        } catch (Throwable $e) {
            $rolledBack = true;

            try {
                $db->rollBack();
            } catch (Throwable) {
                // The rollback itself failed; record that but still surface the
                // original import failure (the real cause) below.
                $rolledBack = false;
            }

            $this->logger->error('Database import failed', [
                'connection' => $connection ?? 'default',
                'statements' => count($statements),
                'rolled_back' => $rolledBack,
            ]);

            throw new RuntimeException('Database import failed: ' . $e->getMessage(), 0, $e);
        }

        $this->logger->info('Database import completed', [
            'connection' => $connection ?? 'default',
            'statements' => count($statements),
        ]);

        return count($statements);
    }

    /**
     * Resolve and validate the source path, returning its real path.
     *
     * @throws InvalidPathException
     */
    private function validatePath(string $path): string
    {
        if (!$this->isSafePath($path)) {
            throw InvalidPathException::directoryTraversal($path);
        }

        $real = realpath($path);

        if ($real === false || !is_file($real) || !is_readable($real)) {
            throw InvalidPathException::create($path, 'SQL file does not exist or is not readable');
        }

        if (strtolower(pathinfo($real, PATHINFO_EXTENSION)) !== 'sql') {
            throw InvalidPathException::create($path, 'Only .sql files may be imported');
        }

        return $real;
    }

    /**
     * Split a SQL dump into individual statements, stripping line comments and
     * blank lines. Naive but sufficient for `.sql` dumps; semicolons inside
     * string literals are out of scope (use a real backup tool for those).
     *
     * @return list<string>
     */
    private function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql);
        $clean = [];

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $clean[] = $line;
        }

        $joined = implode("\n", $clean);

        $statements = [];
        foreach (explode(';', $joined) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $statements[] = $chunk;
            }
        }

        return $statements;
    }
}
