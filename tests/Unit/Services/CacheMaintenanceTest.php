<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Mockery;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Enums\CacheAction;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Services\CacheOptimizationResult;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Covers the maintenance half of {@see CacheService}: event lifecycle,
 * resilience (never throws; logs + dispatches failed), and the orchestrators.
 */
class CacheMaintenanceTest extends TestCase
{
    public function test_a_clear_dispatches_clearing_then_cleared(): void
    {
        Event::fake([CacheEvents::class]);

        (new CacheService(60, []))->clearConfig();

        Event::assertDispatched(
            CacheEvents::class,
            fn (CacheEvents $e): bool => $e->action === CacheAction::Clearing && ($e->metadata['operation'] ?? null) === 'config',
        );
        Event::assertDispatched(
            CacheEvents::class,
            fn (CacheEvents $e): bool => $e->action === CacheAction::Cleared && ($e->metadata['operation'] ?? null) === 'config',
        );
    }

    public function test_a_failing_op_logs_and_dispatches_failed_without_throwing(): void
    {
        Event::fake([CacheEvents::class]);

        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('exists')->andThrow(new RuntimeException('disk gone'));

        $logger = new CollectingLogger();
        $service = new CacheService(60, [], $logger, '', $files);

        // Must not throw.
        $result = $service->clearConfig();

        $this->assertInstanceOf(CacheService::class, $result);
        $this->assertNotEmpty($logger->errors);
        Event::assertDispatched(
            CacheEvents::class,
            fn (CacheEvents $e): bool => $e->action === CacheAction::Failed && ($e->metadata['operation'] ?? null) === 'config',
        );
    }

    public function test_clear_optimization_returns_a_successful_result(): void
    {
        // Delete-only orchestrator — safe against the real app (no writes).
        $result = (new CacheService(60, []))->clearOptimization();

        $this->assertInstanceOf(CacheOptimizationResult::class, $result);
        $this->assertTrue($result->successful());
        $this->assertSame(['config_cleared', 'routes_cleared', 'views_cleared'], $result->steps);
        $this->assertSame([], $result->errors);
    }

    public function test_optimize_visits_every_step(): void
    {
        // Mock the filesystem so config-rebuild/view-compile perform no real
        // writes. The config-cache step needs a real temp file to validate, so
        // it lands in errors here — the point is that all six steps are visited.
        $files = Mockery::mock(Filesystem::class);
        $files->shouldReceive('exists')->andReturn(false);
        $files->shouldReceive('glob')->andReturn([]);
        $files->shouldReceive('isDirectory')->andReturn(false);
        $files->shouldReceive('put')->andReturn(true);

        $result = (new CacheService(60, [], null, '', $files))->optimize();

        $this->assertInstanceOf(CacheOptimizationResult::class, $result);

        $attempted = array_merge($result->steps, array_keys($result->errors));
        sort($attempted);
        $this->assertSame(
            ['config_cached', 'config_cleared', 'framework_cache_cleared', 'routes_cleared', 'views_cleared', 'views_compiled'],
            $attempted,
        );
    }

    public function test_cache_config_never_leaves_a_broken_cache(): void
    {
        Event::fake([CacheEvents::class]);

        // An anonymous class instance exports to PHP that cannot be re-parsed,
        // so its cached form would fatal on load — the classic "bricked cached
        // config" case.
        config()->set('demo.unserializable', new class() {});
        $path = $this->app->getCachedConfigPath();
        @unlink($path);

        try {
            // Must not throw, and must not leave a broken cache file behind.
            (new CacheService(60, []))->cacheConfig();

            $this->assertFileDoesNotExist($path);
            Event::assertDispatched(
                CacheEvents::class,
                fn (CacheEvents $e): bool => $e->action === CacheAction::Failed && ($e->metadata['operation'] ?? null) === 'config_cache',
            );
        } finally {
            @unlink($path);
        }
    }

    public function test_key_from_request_is_a_stable_hash(): void
    {
        $service = new CacheService(60, []);

        $first = $service->keyFromRequest();
        $second = $service->keyFromRequest();

        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $first);
        $this->assertSame($first, $second);
    }
}

/**
 * Minimal PSR-3 logger recording `error`-level messages.
 */
class CollectingLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $errors = [];

    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param array<mixed>       $context
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level === 'error') {
            $this->errors[] = (string) $message;
        }
    }
}
