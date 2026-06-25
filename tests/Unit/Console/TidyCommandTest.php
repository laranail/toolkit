<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Illuminate\Contracts\Console\Kernel;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class TidyCommandTest extends TestCase
{
    private array $created = [];

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_file($path) || is_link($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function makeStorageFile(string $relative, string $contents = 'x'): string
    {
        $path = storage_path($relative);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, $contents);
        $this->created[] = $path;

        return $path;
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function test_command_is_registered_under_namespaced_name_and_alias(): void
    {
        $all = collect($this->app[Kernel::class]->all());

        $this->assertTrue($all->has('laranail::toolkit.tidy'));
        $this->assertTrue($all->has('tidy'));
    }

    public function test_invalid_action_fails(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'nope'])
            ->expectsOutputToContain('Invalid action')
            ->assertExitCode(1);
    }

    // -----------------------------------------------------------------------
    // logs / temp / storage
    // -----------------------------------------------------------------------

    public function test_logs_action_deletes_log_files_in_storage(): void
    {
        // A uniquely-named log file so the console lifecycle logger (which may
        // (re)write laravel.log during the run) cannot mask the deletion.
        $log = $this->makeStorageFile('logs/tidy_' . uniqid() . '.log', 'old log');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($log);
    }

    public function test_gitignore_is_never_deleted(): void
    {
        $keep = $this->makeStorageFile('logs/.gitignore', '*');
        $this->makeStorageFile('logs/app.log', 'log');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists($keep);
    }

    public function test_age_filter_keeps_recent_files(): void
    {
        $recent = $this->makeStorageFile('logs/recent.log', 'recent');

        // Keep only files older than 30 days — the just-created file survives.
        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--days' => '30', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists($recent);
    }

    public function test_temp_action_deletes_temp_files(): void
    {
        $tmp = $this->makeStorageFile('app/temp/old.tmp', 'tmp');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'temp', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($tmp);
    }

    public function test_storage_action_deletes_uploaded_files(): void
    {
        $upload = $this->makeStorageFile('app/uploads/photo.bin', 'bytes');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($upload);
    }

    public function test_size_filter_only_deletes_large_files(): void
    {
        $small = $this->makeStorageFile('logs/small.log', 'x');                 // 1 byte
        $big = $this->makeStorageFile('logs/big.log', str_repeat('y', 3 * 1024 * 1024)); // 3 MB

        // Delete only files >= 2 MB.
        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--size' => '2', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileExists($small);
        $this->assertFileDoesNotExist($big);
    }

    public function test_dry_run_deletes_nothing_and_reports(): void
    {
        $log = $this->makeStorageFile('logs/dry.log', 'log');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertExitCode(0);

        $this->assertFileExists($log);
    }

    // -----------------------------------------------------------------------
    // db gating
    // -----------------------------------------------------------------------

    public function test_db_action_requires_force(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'db'])
            ->expectsOutputToContain('re-run with --force')
            ->assertExitCode(1);
    }

    public function test_db_action_dry_run_is_noop(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'db', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertExitCode(0);
    }

    public function test_db_action_with_force_runs_migrate_fresh(): void
    {
        // The in-memory sqlite test DB makes migrate:fresh safe to actually run.
        // --force satisfies the gate and confirmToProceed() is a no-op outside
        // production, so the real refresh path executes and reports success.
        $this->artisan('laranail::toolkit.tidy', ['action' => 'db', '--force' => true])
            ->expectsOutputToContain('Database refreshed')
            ->assertExitCode(0);

        // migrate:fresh re-runs the package's own filesystem migrations, so its
        // tables exist after the refresh.
        $this->assertTrue(\Schema::hasTable('access_logs'));
    }

    public function test_all_excludes_db_action(): void
    {
        // `all` must not drop tables: the users table (from loadLaravelMigrations)
        // still exists afterwards.
        $this->artisan('laranail::toolkit.tidy', ['action' => 'all', '--force' => true])
            ->expectsOutputToContain('db excluded')
            ->assertExitCode(0);

        $this->assertTrue(\Schema::hasTable('users'));
    }

    // -----------------------------------------------------------------------
    // cache
    // -----------------------------------------------------------------------

    public function test_cache_action_succeeds(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'cache', '--force' => true])
            ->assertExitCode(0);
    }

    public function test_cache_dry_run_previews(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'cache', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertExitCode(0);
    }

    public function test_cache_optimize_flag_also_clears_optimized_caches(): void
    {
        // --optimize runs optimize:clear alongside the cache flush.
        $this->artisan('laranail::toolkit.tidy', ['action' => 'cache', '--optimize' => true, '--force' => true])
            ->assertExitCode(0);
    }

    public function test_cache_dry_run_optimize_is_previewed_in_message(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'cache', '--optimize' => true, '--dry-run' => true])
            ->expectsOutputToContain('optimize:clear')
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------------
    // non-interactive confirmation (declines without --force)
    // -----------------------------------------------------------------------

    public function test_logs_without_force_in_non_interactive_mode_deletes_nothing(): void
    {
        // --no-interaction (no --force): the interaction service is put in
        // non-interactive mode and confirmAction() returns the default (false),
        // so the destructive sweep is declined and the file survives — a piped/CI
        // run never silently deletes.
        $keep = $this->makeStorageFile('logs/noforce_' . uniqid() . '.log', 'keep');

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertFileExists($keep);
    }

    // -----------------------------------------------------------------------
    // combined age + size filter
    // -----------------------------------------------------------------------

    public function test_age_filter_deletes_files_older_than_threshold(): void
    {
        $old = $this->makeStorageFile('logs/aged_' . uniqid() . '.log', 'old');
        // Backdate well beyond the threshold so the age branch deletes it.
        touch($old, time() - (40 * 86400));

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--days' => '30', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($old);
    }

    // -----------------------------------------------------------------------
    // Security
    // -----------------------------------------------------------------------

    #[Group('security')]
    public function test_symlink_escaping_storage_is_not_deleted(): void
    {
        $outsideDir = sys_get_temp_dir() . '/laranail-tidy-out-' . uniqid();
        @mkdir($outsideDir, 0777, true);
        $secret = $outsideDir . '/secret.log';
        file_put_contents($secret, 'do-not-delete');

        // A symlink inside storage/logs pointing at the external secret.
        $escape = storage_path('logs/escape.log');
        @mkdir(dirname($escape), 0777, true);
        @symlink($secret, $escape);
        $this->created[] = $escape;

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        // The external target survives; only in-tree files are removed.
        $this->assertFileExists($secret);
        $this->assertSame('do-not-delete', file_get_contents($secret));

        @unlink($escape);
        @unlink($secret);
        @rmdir($outsideDir);
    }

    #[Group('security')]
    public function test_deletion_is_confined_to_storage_path_in_source(): void
    {
        $code = $this->executableSource(dirname(__DIR__, 3) . '/src/Commands/Tidy.php');

        // Roots are storage-relative; deletion uses realpath containment and the
        // FilePathGuard; the executable code never sweeps sys_get_temp_dir().
        $this->assertStringContainsString('realpath(storage_path())', $code);
        $this->assertStringContainsString('$this->isWithin($real, $root)', $code);
        $this->assertStringContainsString('use FilePathGuard;', $code);
        $this->assertStringNotContainsString('sys_get_temp_dir()', $code);
    }

    /**
     * Return a file's source with comments/docblocks stripped, so assertions
     * reflect executable code (not prose that mentions a pattern).
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
}
