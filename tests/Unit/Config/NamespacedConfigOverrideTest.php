<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Config;

use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Proves the namespaced `laranail.toolkit.*` alias reflects PUBLISHED OVERRIDES,
 * not just package defaults. A value set on the flat (canonical) key before the
 * provider boots — exactly what publishing `config/laranail-toolkit.php` and
 * editing it does — must surface under the dotted namespace too.
 */
final class NamespacedConfigOverrideTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Simulate a user's published-config override on the canonical flat key,
        // applied before the package provider boots (its mirror runs in boot()).
        $app['config']->set('laranail-toolkit.override_probe', 'PUBLISHED_VALUE');
        $app['config']->set('laranail-toolkit.cache.default_expiration', 999);
    }

    public function test_namespaced_key_reflects_a_flat_published_override(): void
    {
        // Canonical flat key carries the override...
        self::assertSame('PUBLISHED_VALUE', config('laranail-toolkit.override_probe'));

        // ...and the dotted namespaced alias mirrors it — so overrides flow through.
        self::assertSame('PUBLISHED_VALUE', config('laranail.toolkit.override_probe'));
    }

    public function test_namespaced_alias_reflects_overridden_nested_value(): void
    {
        self::assertSame(999, config('laranail-toolkit.cache.default_expiration'));
        self::assertSame(999, config('laranail.toolkit.cache.default_expiration'));
    }
}
