<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * `tidy storage` sweeps `app/public`, `app/uploads` and `app/exports`. The
 * first of those is the disk behind `storage:link` — user uploads.
 *
 * With no `--days`/`--size` every file matched, and `--force` skipped the
 * prompt, so `tidy storage --force` and `tidy all --force` deleted the lot.
 * The containment guard had nothing to say about it: those roots are inside
 * `storage_path()`, which is the only question containment answers.
 *
 * `--force` cannot be the gate. It is in every CI invocation and in most of
 * this command's own tests, so it is typed by habit. The gate is a filter, or
 * a flag that exists for nothing else.
 */
final class TidyUserFileGuardTest extends TestCase
{
    /** @var list<string> */
    private array $created = [];

    private ?string $originalEnv = null;

    protected function tearDown(): void
    {
        foreach ($this->created as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        // Restore before parent::tearDown(): Testbench rolls migrations back
        // there, and `migrate:rollback` runs confirmToProceed(), which prompts
        // in production against a mocked output that expects no questions.
        if ($this->originalEnv !== null) {
            $this->app['env'] = $this->originalEnv;
        }

        parent::tearDown();
    }

    private function pretendProduction(): void
    {
        $this->originalEnv = (string) $this->app['env'];
        $this->app['env'] = 'production';
    }

    private function upload(string $relative = 'app/public/invoice.pdf'): string
    {
        $path = storage_path($relative);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'a user uploaded this');
        $this->created[] = $path;

        return $path;
    }

    #[Test]
    public function an_unfiltered_storage_sweep_is_refused_even_with_force(): void
    {
        $upload = $this->upload();

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--force' => true])
            ->expectsOutputToContain('Refusing to sweep')
            ->assertExitCode(1);

        $this->assertFileExists($upload);
    }

    #[Test]
    public function the_refusal_names_the_roots_it_declined(): void
    {
        $this->upload();

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--force' => true])
            ->expectsOutputToContain('storage/app/public')
            ->assertExitCode(1);
    }

    #[Test]
    public function an_age_filter_makes_the_sweep_intentional(): void
    {
        $stale = $this->upload();
        touch($stale, time() - (60 * 86400));

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--days' => '30', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($stale);
    }

    #[Test]
    public function a_size_filter_makes_the_sweep_intentional(): void
    {
        $big = $this->upload('app/exports/report.csv');
        file_put_contents($big, str_repeat('y', 3 * 1024 * 1024));

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--size' => '2', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($big);
    }

    #[Test]
    public function unfiltered_is_the_explicit_way_through(): void
    {
        // Deleting everything remains reachable — it just cannot be reached by
        // a flag anyone types for another reason.
        $upload = $this->upload();

        $this->artisan('laranail::toolkit.tidy', [
            'action' => 'storage',
            '--unfiltered' => true,
            '--force' => true,
        ])->assertExitCode(0);

        $this->assertFileDoesNotExist($upload);
    }

    #[Test]
    public function unfiltered_is_refused_outright_in_production(): void
    {
        // No override, deliberately. --force bypasses Laravel's own
        // confirmToProceed() by design, so a prompt would not be a gate here.
        $this->pretendProduction();

        $upload = $this->upload();

        $this->artisan('laranail::toolkit.tidy', [
            'action' => 'storage',
            '--unfiltered' => true,
            '--force' => true,
        ])
            ->expectsOutputToContain('in production')
            ->assertExitCode(1);

        $this->assertFileExists($upload);
    }

    #[Test]
    public function a_scoped_sweep_still_runs_in_production(): void
    {
        $this->pretendProduction();

        $stale = $this->upload();
        touch($stale, time() - (60 * 86400));

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--days' => '30', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($stale);
    }

    #[Test]
    public function tidy_all_skips_storage_rather_than_failing(): void
    {
        // `all` exists for logs and temp. Failing the whole run over storage
        // would make the useful part unreachable, so it skips — and says so,
        // because an omission this consequential should not have to be
        // inferred from a file count.
        $upload = $this->upload();
        $log = storage_path('logs/tidy-all-guard.log');
        @mkdir(dirname($log), 0777, true);
        file_put_contents($log, 'noise');
        $this->created[] = $log;

        $this->artisan('laranail::toolkit.tidy', ['action' => 'all', '--force' => true])
            ->expectsOutputToContain('storage skipped')
            ->assertExitCode(0);

        $this->assertFileExists($upload, 'tidy all --force deleted a user upload.');
        $this->assertFileDoesNotExist($log, 'tidy all stopped sweeping logs.');
    }

    #[Test]
    public function tidy_all_sweeps_storage_when_scoped(): void
    {
        $stale = $this->upload();
        touch($stale, time() - (60 * 86400));

        $this->artisan('laranail::toolkit.tidy', ['action' => 'all', '--days' => '30', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($stale);
    }

    #[Test]
    public function logs_and_temp_are_unaffected_by_the_guard(): void
    {
        // They hold regenerable data, which is what makes an unfiltered sweep
        // the whole point of the command there.
        $log = storage_path('logs/regenerable.log');
        @mkdir(dirname($log), 0777, true);
        file_put_contents($log, 'noise');
        $this->created[] = $log;

        $this->artisan('laranail::toolkit.tidy', ['action' => 'logs', '--force' => true])
            ->assertExitCode(0);

        $this->assertFileDoesNotExist($log);
    }

    #[Test]
    public function a_dry_run_is_refused_too_rather_than_previewing_the_lot(): void
    {
        // A preview is harmless, but it would print every file in app/public as
        // "would delete" — which reads as a plan the next --force will carry
        // out, and that plan is the bug.
        $upload = $this->upload();

        $this->artisan('laranail::toolkit.tidy', ['action' => 'storage', '--dry-run' => true])
            ->expectsOutputToContain('Refusing to sweep')
            ->assertExitCode(1);

        $this->assertFileExists($upload);
    }
}
