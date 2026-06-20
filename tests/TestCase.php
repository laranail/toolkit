<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Simtabi\Laranail\Toolkit\Providers\ToolkitServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations();
    }

    protected function defineDatabaseMigrations(): void
    {
        // Run the package's own migrations (access_logs, model_audits, ...).
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ToolkitServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Database: sqlite in-memory
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Cache: array driver
        $app['config']->set('cache.default', 'array');

        // Queue: sync driver
        $app['config']->set('queue.default', 'sync');

        // Seed sane package config under the real key so the provider's
        // caching/llm bindings resolve in tests.
        $app['config']->set('laranail.toolkit.cache', [
            'default_expiration' => 60,
            'default_tags' => [],
        ]);
    }
}
