<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator;

class RuntimeConfiguratorTest extends TestCase
{
    private string $originalMemoryLimit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalMemoryLimit = (string) ini_get('memory_limit');
    }

    protected function tearDown(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);
        parent::tearDown();
    }

    public function test_fluent_setters_populate_pending(): void
    {
        $config = RuntimeConfigurator::make()->memory('256M')->timeout(30);

        $this->assertSame('256M', $config->getPending()['memory_limit']);
        $this->assertSame(30, $config->getPending()['max_execution_time']);
        $this->assertFalse($config->isApplied());
    }

    public function test_presets_configure_memory_timeout_and_tools(): void
    {
        $queue = RuntimeConfigurator::forQueueJob();

        $this->assertSame('1G', $queue->getPending()['memory_limit']);
        $this->assertSame(0, $queue->getPending()['max_execution_time']);
        $this->assertArrayHasKey('telescope', $queue->getDisabledTools());

        $batch = RuntimeConfigurator::forBatchProcessing();
        $this->assertSame('2G', $batch->getPending()['memory_limit']);
        $this->assertEqualsCanonicalizing(
            ['telescope', 'xdebug', 'clockwork', 'debugbar'],
            array_keys($batch->getDisabledTools()),
        );
    }

    public function test_apply_then_restore_round_trips_the_memory_limit(): void
    {
        $config = RuntimeConfigurator::make()->memory('333M');

        $config->apply();
        $this->assertTrue($config->isApplied());
        $this->assertSame('333M', ini_get('memory_limit'));

        $config->restore();
        $this->assertFalse($config->isApplied());
        $this->assertSame($this->originalMemoryLimit, ini_get('memory_limit'));
    }

    public function test_scope_applies_during_the_callback_and_restores_after(): void
    {
        $inside = RuntimeConfigurator::make()->memory('444M')->scope(
            static fn (): string => (string) ini_get('memory_limit'),
        );

        $this->assertSame('444M', $inside);
        $this->assertSame($this->originalMemoryLimit, ini_get('memory_limit'));
    }

    public function test_apply_is_idempotent(): void
    {
        $config = RuntimeConfigurator::make()->memory('222M');

        $config->apply();
        $this->assertTrue($config->isApplied());

        // Second apply is a no-op; state stays applied.
        $config->apply();
        $this->assertTrue($config->isApplied());

        $config->restore();
        $this->assertSame($this->originalMemoryLimit, ini_get('memory_limit'));
    }

    public function test_is_cli_detects_the_test_runner(): void
    {
        $this->assertTrue(RuntimeConfigurator::isCli());
    }

    public function test_non_runtime_ini_directives_are_recorded_not_silently_dropped(): void
    {
        // realpath_cache_size is PHP_INI_SYSTEM — ini_set() refuses it at runtime.
        $config = RuntimeConfigurator::make()->set('realpath_cache_size', '8M');

        $config->apply();

        $this->assertArrayHasKey('realpath_cache_size', $config->getFailedIniSettings());

        $config->restore();
    }
}
