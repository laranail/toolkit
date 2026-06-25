<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Exercises the mysql/mariadb `export` (and `--backup`) path of the database
 * manager WITHOUT a real database or a real mysqldump: a tiny shim binary on a
 * temporary PATH stands in for mysqldump, emitting a fixed dump and exiting 0.
 * This drives the array-arg Process, the credential defaults-file writer, the
 * streamed-output capture and the success/size reporting end to end.
 */
class DatabaseManagerExportTest extends TestCase
{
    private string $binDir;

    private ?string $originalPath = null;

    private array $cleanup = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Register a mysql-driver connection so getDriverName() === 'mysql'.
        // No server is ever contacted — the shim binary needs no DB.
        $this->app['config']->set('database.connections.fake_mysql', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'demo_db',
            'username' => 'demo_user',
            'password' => 's3cr3t-p@ss',
            'prefix' => '',
        ]);

        $this->binDir = sys_get_temp_dir() . '/laranail-bin-' . uniqid();
        @mkdir($this->binDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->originalPath !== null) {
            putenv('PATH=' . $this->originalPath);
            $_SERVER['PATH'] = $this->originalPath;
        }

        foreach ($this->cleanup as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        @unlink($this->binDir . '/mysqldump');
        @rmdir($this->binDir);

        parent::tearDown();
    }

    private function installFakeMysqldump(string $body, int $exitCode = 0): void
    {
        $shim = $this->binDir . '/mysqldump';

        // A POSIX shell shim: print the body to stdout, exit with $exitCode.
        // It also asserts the password is NOT present in its argv (the secret
        // must travel via the defaults-extra-file / MYSQL_PWD, never argv).
        $script = "#!/bin/sh\n"
            . "for arg in \"$@\"; do\n"
            . "  case \"\$arg\" in\n"
            . "    *s3cr3t-p@ss*) echo 'PASSWORD-LEAKED-IN-ARGV' >&2; exit 99;;\n"
            . "  esac\n"
            . "done\n"
            . 'printf %s ' . escapeshellarg($body) . "\n"
            . "exit {$exitCode}\n";

        file_put_contents($shim, $script);
        @chmod($shim, 0755);

        $this->originalPath = getenv('PATH') ?: '';
        putenv('PATH=' . $this->binDir . PATH_SEPARATOR . $this->originalPath);
        $_SERVER['PATH'] = $this->binDir . PATH_SEPARATOR . $this->originalPath;
    }

    public function test_export_runs_mysqldump_and_writes_the_dump(): void
    {
        $this->installFakeMysqldump("-- dump\nSELECT 1;\n");

        $target = sys_get_temp_dir() . '/laranail-export-' . uniqid() . '.sql';
        $this->cleanup[] = $target;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--file' => $target,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFileExists($target);
        $this->assertStringContainsString('SELECT 1;', (string) file_get_contents($target));
    }

    public function test_export_default_target_path_is_derived_from_db_name(): void
    {
        $this->installFakeMysqldump("-- dump\n");

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--force' => true,
        ])->assertExitCode(0);

        $matches = glob(base_path('database_export_demo_db_*.sql')) ?: [];
        $this->assertNotEmpty($matches, 'A default-named export file should be created.');

        foreach ($matches as $m) {
            $this->cleanup[] = $m;
        }
    }

    public function test_export_failure_unlinks_the_partial_target(): void
    {
        // Shim exits non-zero → the command reports failure and removes the file.
        $this->installFakeMysqldump('partial', 1);

        $target = sys_get_temp_dir() . '/laranail-export-fail-' . uniqid() . '.sql';
        $this->cleanup[] = $target;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--file' => $target,
            '--force' => true,
        ])->expectsOutputToContain('Export failed')->assertExitCode(1);

        $this->assertFileDoesNotExist($target);
    }

    public function test_export_dry_run_does_not_invoke_mysqldump(): void
    {
        $this->installFakeMysqldump("should-not-run\n");

        $target = sys_get_temp_dir() . '/laranail-export-dry-' . uniqid() . '.sql';
        $this->cleanup[] = $target;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--file' => $target,
            '--dry-run' => true,
        ])->expectsOutputToContain('[dry-run]')->assertExitCode(0);

        $this->assertFileDoesNotExist($target);
    }

    public function test_restore_with_backup_exports_first(): void
    {
        $this->installFakeMysqldump("-- backup\n");

        // restore --backup runs an export of the current state BEFORE importing.
        // The import then fails (no real mysql server), but the backup must have
        // been written first — which is the path under test here.
        $sql = sys_get_temp_dir() . '/laranail-restore-' . uniqid() . '.sql';
        file_put_contents($sql, "SELECT 1;\n");
        $this->cleanup[] = $sql;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'restore',
            '--connection' => 'fake_mysql',
            '--file' => $sql,
            '--backup' => true,
            '--force' => true,
        ])->assertExitCode(1); // import fails (no server), but backup ran first

        $matches = glob(base_path('database_backup_*.sql')) ?: [];
        $this->assertNotEmpty($matches, 'A backup file should be written before the import.');

        foreach ($matches as $m) {
            $this->cleanup[] = $m;
        }
    }

    #[Group('security')]
    public function test_password_is_never_passed_on_the_mysqldump_argv(): void
    {
        // The shim exits 99 with 'PASSWORD-LEAKED-IN-ARGV' if it ever sees the
        // password in its arguments. A clean exit proves the secret travelled via
        // the defaults-extra-file / MYSQL_PWD, never argv.
        $this->installFakeMysqldump("-- ok\n");

        $target = sys_get_temp_dir() . '/laranail-export-sec-' . uniqid() . '.sql';
        $this->cleanup[] = $target;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--file' => $target,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertStringNotContainsString('PASSWORD-LEAKED', (string) file_get_contents($target));
    }

    public function test_export_missing_binary_reports_failure(): void
    {
        // No fake binary installed and the host has no mysqldump → graceful fail.
        if (new ExecutableFinder()->find('mysqldump') !== null) {
            $this->markTestSkipped('A real mysqldump is on PATH; cannot assert the missing-binary path.');
        }

        $target = sys_get_temp_dir() . '/laranail-export-nobin-' . uniqid() . '.sql';
        $this->cleanup[] = $target;

        $this->artisan('laranail::toolkit.database', [
            'action' => 'export',
            '--connection' => 'fake_mysql',
            '--file' => $target,
            '--force' => true,
        ])->expectsOutputToContain('Export failed')->assertExitCode(1);
    }
}
