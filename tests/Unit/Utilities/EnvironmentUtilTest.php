<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\EnvironmentUtil;

class EnvironmentUtilTest extends TestCase
{
    public function test_reports_the_testing_environment(): void
    {
        $this->app['env'] = 'testing';

        $this->assertSame('testing', EnvironmentUtil::current());
        $this->assertTrue(EnvironmentUtil::isTesting());
        $this->assertTrue(EnvironmentUtil::isNonProduction());
        $this->assertFalse(EnvironmentUtil::isProduction());
        $this->assertTrue(EnvironmentUtil::isEnvironment('testing'));
        $this->assertTrue(EnvironmentUtil::isEnvironment(['production', 'testing']));
        $this->assertFalse(EnvironmentUtil::isEnvironment('production'));
    }

    public function test_named_buckets(): void
    {
        $this->app['env'] = 'staging';
        $this->assertTrue(EnvironmentUtil::isStaging());
        $this->assertTrue(EnvironmentUtil::isLocal()); // staging counts as local-ish
        $this->assertTrue(EnvironmentUtil::isNonProduction());

        $this->app['env'] = 'beta';
        $this->assertTrue(EnvironmentUtil::isBeta());

        $this->app['env'] = 'alpha';
        $this->assertTrue(EnvironmentUtil::isAlpha());

        $this->app['env'] = 'release';
        $this->assertTrue(EnvironmentUtil::isRelease());
    }

    public function test_production_environment(): void
    {
        // Detect production without mutating the global env (which would make
        // Testbench's DB teardown prompt the production confirmation).
        $this->app->detectEnvironment(static fn (): string => 'production');

        $this->assertTrue(EnvironmentUtil::isProduction());
        $this->assertFalse(EnvironmentUtil::isNonProduction());
        $this->assertFalse(EnvironmentUtil::isLocal());

        // Restore so the rest of the suite/teardown sees the testing env.
        $this->app->detectEnvironment(static fn (): string => 'testing');
    }

    public function test_running_unit_tests(): void
    {
        $this->assertTrue(EnvironmentUtil::isRunningUnitTests());
    }
}
