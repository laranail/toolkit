<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class DatabaseManagerCommandTest extends TestCase
{
    private array $cleanup = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function track(string $path): string
    {
        $this->cleanup[] = $path;

        return $path;
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function test_command_is_registered_under_namespaced_name_and_alias(): void
    {
        $all = collect($this->app[Kernel::class]->all());

        $this->assertTrue($all->has('laranail::toolkit.database'));
        $this->assertTrue($all->has('laranail:database'));
    }

    public function test_invalid_action_fails(): void
    {
        $this->artisan('laranail::toolkit.database', ['action' => 'nope'])
            ->expectsOutputToContain('Invalid action')
            ->assertExitCode(1);
    }

    // -----------------------------------------------------------------------
    // import / restore
    // -----------------------------------------------------------------------

    public function test_import_requires_a_file(): void
    {
        $this->artisan('laranail::toolkit.database', ['action' => 'import', '--force' => true])
            ->expectsOutputToContain('--file')
            ->assertExitCode(1);
    }

    public function test_import_executes_sql_and_reports_statement_count(): void
    {
        $sql = $this->track(sys_get_temp_dir() . '/laranail_import_' . uniqid() . '.sql');
        file_put_contents($sql, "CREATE TABLE widgets (id integer primary key);\n"
            . "INSERT INTO widgets (id) VALUES (1);\n");

        $this->artisan('laranail::toolkit.database', [
            'action' => 'import',
            '--file' => $sql,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('widgets'));
        $this->assertSame(1, \DB::table('widgets')->count());
    }

    public function test_import_dry_run_executes_nothing(): void
    {
        $sql = $this->track(sys_get_temp_dir() . '/laranail_import_' . uniqid() . '.sql');
        file_put_contents($sql, "CREATE TABLE gadgets (id integer primary key);\n");

        $this->artisan('laranail::toolkit.database', [
            'action' => 'import',
            '--file' => $sql,
            '--dry-run' => true,
        ])->expectsOutputToContain('[dry-run]')->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('gadgets'));
    }

    public function test_restore_is_import(): void
    {
        $sql = $this->track(sys_get_temp_dir() . '/laranail_restore_' . uniqid() . '.sql');
        file_put_contents($sql, "CREATE TABLE restored (id integer primary key);\n");

        $this->artisan('laranail::toolkit.database', [
            'action' => 'restore',
            '--file' => $sql,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('restored'));
    }

    // -----------------------------------------------------------------------
    // clean
    // -----------------------------------------------------------------------

    public function test_clean_truncates_named_table(): void
    {
        \DB::statement('CREATE TABLE cleanme (id integer primary key)');
        \DB::table('cleanme')->insert(['id' => 1]);
        \DB::table('cleanme')->insert(['id' => 2]);

        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--tables' => 'cleanme',
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, \DB::table('cleanme')->count());
    }

    public function test_clean_skips_unknown_table(): void
    {
        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--tables' => 'definitely_not_a_table',
            '--force' => true,
        ])
            ->expectsOutputToContain('Skipping unknown table')
            ->assertExitCode(1);
    }

    public function test_clean_without_tables_truncates_every_table_on_the_connection(): void
    {
        \DB::statement('CREATE TABLE alpha (id integer primary key)');
        \DB::statement('CREATE TABLE beta (id integer primary key)');
        \DB::table('alpha')->insert(['id' => 1]);
        \DB::table('beta')->insert(['id' => 1]);

        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--force' => true,
        ])->assertExitCode(0);

        // Every table resolved via the schema builder is truncated.
        $this->assertSame(0, \DB::table('alpha')->count());
        $this->assertSame(0, \DB::table('beta')->count());
    }

    public function test_backup_on_non_mysql_connection_is_skipped_with_a_warning(): void
    {
        \DB::statement('CREATE TABLE gamma (id integer primary key)');
        \DB::table('gamma')->insert(['id' => 1]);

        // --backup on the sqlite default warns and proceeds (no native backup).
        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--tables' => 'gamma',
            '--backup' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('native backup is mysql/mariadb only')
            ->assertExitCode(0);

        $this->assertSame(0, \DB::table('gamma')->count());
    }

    public function test_clean_dry_run_does_not_truncate(): void
    {
        \DB::statement('CREATE TABLE keepme (id integer primary key)');
        \DB::table('keepme')->insert(['id' => 1]);

        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--tables' => 'keepme',
            '--dry-run' => true,
        ])->expectsOutputToContain('[dry-run]')->assertExitCode(0);

        $this->assertSame(1, \DB::table('keepme')->count());
    }

    // -----------------------------------------------------------------------
    // export (driver-aware)
    // -----------------------------------------------------------------------

    public function test_export_is_mysql_only_and_suggests_db_dumper_on_sqlite(): void
    {
        $this->artisan('laranail::toolkit.database', ['action' => 'export', '--force' => true])
            ->expectsOutputToContain('mysql/mariadb only')
            ->assertExitCode(1);
    }

    // -----------------------------------------------------------------------
    // Security
    // -----------------------------------------------------------------------

    #[Group('security')]
    public function test_malicious_table_name_is_rejected_via_schema_not_executed(): void
    {
        \DB::statement('CREATE TABLE legit (id integer primary key)');
        \DB::table('legit')->insert(['id' => 1]);

        // A SQL-injection style table value must be rejected by Schema::hasTable
        // (it is not a real table) — never interpolated into a TRUNCATE.
        $this->artisan('laranail::toolkit.database', [
            'action' => 'clean',
            '--tables' => 'legit; DROP TABLE legit;--',
            '--force' => true,
        ])
            ->expectsOutputToContain('Skipping unknown table')
            ->assertExitCode(1);

        // The legit table and its data survive — no injected statement ran.
        $this->assertTrue(Schema::hasTable('legit'));
        $this->assertSame(1, \DB::table('legit')->count());
    }

    #[Group('security')]
    public function test_unsafe_import_file_path_is_rejected(): void
    {
        $this->artisan('laranail::toolkit.database', [
            'action' => 'import',
            '--file' => '../../etc/passwd',
            '--force' => true,
        ])
            ->expectsOutputToContain('not found or unsafe path')
            ->assertExitCode(1);
    }

    #[Group('security')]
    public function test_clean_truncate_is_grammar_compiled_not_raw_string_sql(): void
    {
        // The clean action compiles its truncate through the query builder, so a
        // table name is grammar-wrapped, never interpolated into a raw SQL
        // string. Strip comments first so this checks the executable code only.
        $code = $this->executableSource(
            dirname(__DIR__, 3) . '/src/Commands/DatabaseManager.php'
        );

        $this->assertStringContainsString('$connection->table($table)->truncate()', $code);
        // No raw shelling in the executable code.
        $this->assertStringNotContainsString('fromShellCommandline', $code);
        $this->assertStringNotContainsString('shell_exec', $code);
        $this->assertStringNotContainsString('proc_open', $code);
        $this->assertDoesNotMatchRegularExpression('/\bexec\s*\(/', $code);
        $this->assertDoesNotMatchRegularExpression('/`[^`]+`/', $code);
    }

    /**
     * Return a file's source with comments and docblocks stripped, so security
     * assertions reflect executable code (not prose that mentions a pattern).
     */
    private function executableSource(string $path): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($path)) as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= $token[1];

                continue;
            }

            $code .= $token;
        }

        return $code;
    }

    #[Group('security')]
    public function test_export_never_puts_password_on_the_command_line(): void
    {
        $code = $this->executableSource(
            dirname(__DIR__, 3) . '/src/Commands/DatabaseManager.php'
        );

        // No `--password=` argument is ever appended to the mysqldump argv; the
        // secret travels via a defaults-extra-file or MYSQL_PWD env, never argv.
        $this->assertDoesNotMatchRegularExpression('/--password=/', $code);
        $this->assertStringContainsString('--defaults-extra-file=', $code);
        $this->assertStringContainsString('MYSQL_PWD', $code);
        // The Process is constructed from an array of args.
        $this->assertStringContainsString('new Process($command', $code);
    }
}
