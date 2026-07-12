<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use ReflectionMethod;
use ReflectionProperty;
use Simtabi\Laranail\Toolkit\Commands\Tidy;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Targets the harder-to-reach Tidy branches: the declined-confirmation early
 * returns, the cache-flush failure catch, the unresolvable storage path, the
 * db-action confirmation/seed paths, and the signal-/guard-driven sweep skips.
 */
class TidyBranchesTest extends TestCase
{
    /** @var list<string> */
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

    private function makeSpyCommand(): SpyTidy
    {
        $spy = new SpyTidy(
            $this->app->make(FileServiceInterface::class),
            $this->app->make(CacheRepositoryInterface::class),
            $this->app->make(LoggerServiceInterface::class),
        );
        $spy->setLaravel($this->app);
        $this->app->make(Kernel::class)->registerCommand($spy);

        return $spy;
    }

    // -----------------------------------------------------------------------
    // Declined confirmations (non-interactive, no --force)
    // -----------------------------------------------------------------------

    public function test_cache_action_declined_in_non_interactive_mode_is_a_noop(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'cache', '--no-interaction' => true])
            ->doesntExpectOutputToContain('Cache tidied.')
            ->assertExitCode(0);
    }

    public function test_all_action_declined_in_non_interactive_mode_is_a_noop(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'all', '--no-interaction' => true])
            ->doesntExpectOutputToContain('All tidied')
            ->assertExitCode(0);
    }

    public function test_all_dry_run_previews_the_cache_flush(): void
    {
        $this->artisan('laranail::toolkit.tidy', ['action' => 'all', '--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Would flush the application cache.')
            ->assertExitCode(0);
    }

    // -----------------------------------------------------------------------
    // Unresolvable storage path
    // -----------------------------------------------------------------------

    public function test_unresolvable_storage_path_fails(): void
    {
        $this->app->useStoragePath('/laranail-nonexistent-' . uniqid());

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->expectsOutputToContain('storage_path() does not resolve')
            ->assertExitCode(1);
    }

    // -----------------------------------------------------------------------
    // Cache-flush failure catch
    // -----------------------------------------------------------------------

    public function test_cache_flush_failure_is_caught_and_the_run_completes(): void
    {
        $spy = $this->makeSpyCommand();
        $spy->throwOnCacheClear = true;

        // cache:clear throws, but the catch logs and the run still reports the
        // cache as tidied (exit success).
        $this->artisan('laranail::toolkit.tidy-spy', ['action' => 'cache', '--force' => true])
            ->expectsOutputToContain('Cache tidied.')
            ->assertExitCode(0);

        $this->assertContains('cache:clear', $spy->calledCommands);
    }

    // -----------------------------------------------------------------------
    // db action: declined confirmation + seed
    // -----------------------------------------------------------------------

    public function test_db_action_fails_when_confirm_to_proceed_declines(): void
    {
        $spy = $this->makeSpyCommand();
        $spy->confirmResult = false;

        $this->artisan('laranail::toolkit.tidy-spy', ['action' => 'db', '--force' => true])
            ->assertExitCode(1);

        // It bailed out before invoking the destructive refresh.
        $this->assertNotContains('migrate:fresh', $spy->calledCommands);
    }

    public function test_db_action_with_seed_runs_migrate_fresh_then_seed(): void
    {
        $spy = $this->makeSpyCommand();

        $this->artisan('laranail::toolkit.tidy-spy', ['action' => 'db', '--force' => true, '--seed' => true])
            ->expectsOutputToContain('Database refreshed.')
            ->assertExitCode(0);

        $this->assertSame(['migrate:fresh', 'db:seed'], $spy->calledCommands);
    }

    // -----------------------------------------------------------------------
    // sweep(): guard + signal skips (driven directly)
    // -----------------------------------------------------------------------

    public function test_sweep_skips_an_unsafe_relative_root(): void
    {
        $keep = $this->makeStorageFile('logs/guarded_' . uniqid() . '.log', 'keep');

        $command = $this->app->make(Tidy::class);
        $base = (string) realpath(storage_path());

        (new ReflectionMethod($command, 'sweep'))->invoke($command, $base, '../escape');

        $this->assertSame(0, $this->filesProcessed($command));
        $this->assertFileExists($keep);
    }

    public function test_sweep_stops_when_a_termination_signal_arrived(): void
    {
        $keep = $this->makeStorageFile('logs/signalled_' . uniqid() . '.log', 'keep');

        $command = $this->app->make(Tidy::class);
        $command->setLaravel($this->app);

        // Provide an input so the age/size option reads resolve to their defaults.
        $input = new ArrayInput([], $command->getDefinition());
        (new ReflectionProperty(Command::class, 'input'))->setValue($command, $input);

        // Flip the running flag off so the per-file loop breaks immediately.
        $services = (new ReflectionProperty($command, 'services'))->getValue($command);
        $services->signals()->stop();

        $base = (string) realpath(storage_path());
        (new ReflectionMethod($command, 'sweep'))->invoke($command, $base, 'logs');

        $this->assertSame(0, $this->filesProcessed($command));
        $this->assertFileExists($keep);
    }

    // -----------------------------------------------------------------------
    // sweep(): non-file entries are skipped (via the normal run)
    // -----------------------------------------------------------------------

    public function test_empty_subdirectories_are_skipped_while_files_are_deleted(): void
    {
        $dir = storage_path('logs/branch_' . uniqid());
        @mkdir($dir . '/emptydir', 0777, true);
        $file = $dir . '/old.log';
        file_put_contents($file, 'log');
        $this->created[] = $file;

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($file);

        @rmdir($dir . '/emptydir');
        @rmdir($dir);
    }

    private function filesProcessed(Tidy $command): int
    {
        $value = (new ReflectionProperty(Tidy::class, 'filesProcessed'))->getValue($command);

        return is_int($value) ? $value : -1;
    }
}

/**
 * A Tidy subclass that records dispatched sub-commands (without running them),
 * can force a cache:clear failure, and can override the production-safety
 * confirmation — exercising the db/cache branches deterministically.
 */
class SpyTidy extends Tidy
{
    /** @var list<string> */
    public array $calledCommands = [];

    public bool $throwOnCacheClear = false;

    public ?bool $confirmResult = null;

    protected $signature = 'laranail::toolkit.tidy-spy
        {action? : What to tidy}
        {--days= : Only delete files older than this many days}
        {--size= : Only delete files larger than this many MB}
        {--seed : also run db:seed}
        {--optimize : also run optimize:clear}
        {--dry-run : Show what would be removed}
        {--force : Skip confirmation prompts}';

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function call($command, array $arguments = [])
    {
        $name = $command instanceof \Symfony\Component\Console\Command\Command
            ? (string) $command->getName()
            : $command;

        $this->calledCommands[] = $name;

        if ($this->throwOnCacheClear && $name === 'cache:clear') {
            throw new \RuntimeException('cache flush failed');
        }

        return self::SUCCESS;
    }

    public function confirmToProceed($warning = 'Application In Production', $callback = null)
    {
        if ($this->confirmResult !== null) {
            return $this->confirmResult;
        }

        return parent::confirmToProceed($warning, $callback);
    }
}
