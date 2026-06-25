<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Simtabi\Laranail\Toolkit\Support\Environment;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_reports_the_testing_environment(): void
    {
        $this->app['env'] = 'testing';

        $this->assertSame('testing', Environment::current());
        $this->assertTrue(Environment::isTesting());
        $this->assertTrue(Environment::isNonProduction());
        $this->assertFalse(Environment::isProduction());
        $this->assertTrue(Environment::isEnvironment('testing'));
        $this->assertTrue(Environment::isEnvironment(['production', 'testing']));
        $this->assertFalse(Environment::isEnvironment('production'));
    }

    public function test_named_buckets(): void
    {
        $this->app['env'] = 'staging';
        $this->assertTrue(Environment::isStaging());
        $this->assertTrue(Environment::isLocal()); // staging counts as local-ish
        $this->assertTrue(Environment::isNonProduction());

        $this->app['env'] = 'beta';
        $this->assertTrue(Environment::isBeta());

        $this->app['env'] = 'alpha';
        $this->assertTrue(Environment::isAlpha());

        $this->app['env'] = 'release';
        $this->assertTrue(Environment::isRelease());
    }

    public function test_production_environment(): void
    {
        // Detect production without mutating the global env (which would make
        // Testbench's DB teardown prompt the production confirmation).
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->assertTrue(Environment::isProduction());
        $this->assertFalse(Environment::isNonProduction());
        $this->assertFalse(Environment::isLocal());

        // Restore so the rest of the suite/teardown sees the testing env.
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }

    public function test_running_unit_tests(): void
    {
        $this->assertTrue(Environment::isRunningUnitTests());
    }
}
