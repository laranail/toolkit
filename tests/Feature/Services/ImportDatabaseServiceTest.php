<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Services;

use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException;
use Simtabi\Laranail\Toolkit\Services\Contracts\ImportDatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\ImportDatabaseService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class ImportDatabaseServiceTest extends TestCase
{
    private function sqlFile(string $sql): string
    {
        $path = sys_get_temp_dir() . '/laranail-import-' . uniqid() . '.sql';
        file_put_contents($path, $sql);

        return $path;
    }

    public function test_interface_is_bound_to_the_concrete(): void
    {
        $this->assertInstanceOf(
            ImportDatabaseService::class,
            $this->app->make(ImportDatabaseServiceInterface::class),
        );
    }

    public function test_imports_statements_inside_a_transaction(): void
    {
        $service = $this->app->make(ImportDatabaseServiceInterface::class);

        $path = $this->sqlFile(<<<'SQL'
            -- a comment line
            CREATE TABLE gadgets (id INTEGER PRIMARY KEY, name TEXT);
            INSERT INTO gadgets (name) VALUES ('alpha');
            INSERT INTO gadgets (name) VALUES ('beta');
            SQL);

        try {
            $count = $service->import($path);

            $this->assertSame(3, $count);
            $this->assertSame(2, $this->app->make('db')->table('gadgets')->count());
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_file_imports_zero_statements(): void
    {
        $service = $this->app->make(ImportDatabaseServiceInterface::class);
        $path = $this->sqlFile("-- nothing here\n\n");

        try {
            $this->assertSame(0, $service->import($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_traversal_paths(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->app->make(ImportDatabaseServiceInterface::class)->import('../../etc/passwd.sql');
    }

    public function test_rejects_non_sql_extension(): void
    {
        $service = $this->app->make(ImportDatabaseServiceInterface::class);
        $path = sys_get_temp_dir() . '/laranail-import-' . uniqid() . '.txt';
        file_put_contents($path, 'SELECT 1;');

        try {
            $this->expectException(InvalidPathException::class);
            $service->import($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_rejects_missing_file(): void
    {
        $this->expectException(InvalidPathException::class);

        $this->app->make(ImportDatabaseServiceInterface::class)
            ->import(sys_get_temp_dir() . '/laranail-missing-' . uniqid() . '.sql');
    }

    public function test_rolls_back_on_a_failing_statement(): void
    {
        $service = $this->app->make(ImportDatabaseServiceInterface::class);

        $path = $this->sqlFile(<<<'SQL_WRAP'
        CREATE TABLE doohickeys (id INTEGER PRIMARY KEY, name TEXT);
        INSERT INTO doohickeys (name) VALUES ('ok');
        THIS IS NOT VALID SQL;
        SQL_WRAP);

        try {
            $this->expectException(RuntimeException::class);
            $service->import($path);
        } finally {
            @unlink($path);
            // The table creation was rolled back with the whole import.
            $this->assertFalse($this->app->make('db')->getSchemaBuilder()->hasTable('doohickeys'));
        }
    }
}
