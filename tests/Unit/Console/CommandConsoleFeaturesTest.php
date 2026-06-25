<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Console;

use Simtabi\Laranail\Toolkit\Commands\Tidy;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Exercises the console-toolkit features the maintenance commands adopt from
 * `laranail/console`: the `$this->services` lifecycle (metadata, signals,
 * performance, logger) and the fluent `consoleWriter()` output. Signal handling
 * is driven through the service's running flag — no real OS signal is raised, so
 * these pass with or without ext-pcntl.
 */
class CommandConsoleFeaturesTest extends TestCase
{
    /**
     * Resolve a registered command, bind a buffered output and run it with the
     * given input array — returning the exit code so the caller can assert on
     * both the effect and the populated command metadata.
     *
     * @param array<string, mixed> $input
     */
    private function runCommand(object $command, array $input, ?callable $before = null): int
    {
        $command->setLaravel($this->app);
        $command->setApplication(new Application());

        if ($before !== null) {
            $before($command);
        }

        return $command->run(new ArrayInput($input), new BufferedOutput());
    }

    // -----------------------------------------------------------------------
    // metadata is populated through the lifecycle
    // -----------------------------------------------------------------------

    public function test_tidy_records_files_processed_and_space_freed_metadata(): void
    {
        $log = storage_path('logs/meta_' . uniqid() . '.log');
        @mkdir(dirname($log), 0777, true);
        file_put_contents($log, str_repeat('x', 128));

        /** @var Tidy $command */
        $command = $this->app->make(Tidy::class);

        $exit = $this->runCommand($command, ['action' => 'logs', '--force' => true]);

        $this->assertSame(0, $exit);

        $metadata = $command->getServices()->metadata();
        $this->assertSame('logs', $metadata->get('action'));
        $this->assertGreaterThanOrEqual(1, $metadata->get('files_processed'));
        $this->assertIsString($metadata->get('space_freed'));
        $this->assertFileDoesNotExist($log);
    }

    // -----------------------------------------------------------------------
    // signal-safe sweep / clean loop (driven via the service flag, no OS signal)
    // -----------------------------------------------------------------------

    public function test_tidy_sweep_stops_when_termination_requested(): void
    {
        $log = storage_path('logs/keep_' . uniqid() . '.log');
        @mkdir(dirname($log), 0777, true);
        file_put_contents($log, 'keep-me');

        /** @var Tidy $command */
        $command = $this->app->make(Tidy::class);

        // Flip the running flag off before the run so the file sweep bails on the
        // first iteration — no file is deleted. shouldKeepRunning() defaults true,
        // so this isolates the signal-safe break path without an OS signal.
        $exit = $this->runCommand($command, ['action' => 'logs', '--force' => true], static function (Tidy $c): void {
            $c->getServices()->signals()->stop();
        });

        $this->assertSame(0, $exit);
        $this->assertFileExists($log, 'A termination request must stop the sweep before deleting.');

        @unlink($log);
    }

    public function test_tidy_all_stops_sweeping_roots_when_termination_requested(): void
    {
        $upload = storage_path('app/uploads/all_' . uniqid() . '.bin');
        @mkdir(dirname($upload), 0777, true);
        file_put_contents($upload, 'keep-me');

        /** @var Tidy $command */
        $command = $this->app->make(Tidy::class);

        // Stop before the run: tidyAll flushes the cache, then the per-root loop
        // breaks on the first check — so the storage upload is never swept.
        $exit = $this->runCommand($command, ['action' => 'all', '--force' => true], static function (Tidy $c): void {
            $c->getServices()->signals()->stop();
        });

        $this->assertSame(0, $exit);
        $this->assertFileExists($upload);

        @unlink($upload);
    }
}
