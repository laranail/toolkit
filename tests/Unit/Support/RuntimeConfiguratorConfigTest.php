<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Support;

use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * The config-driven path (`fromConfig()`/`usingConfig()`) reading the
 * `laranail.toolkit.runtime` block. These only inspect the pending state — they
 * never `apply()`, so no real INI is mutated.
 */
class RuntimeConfiguratorConfigTest extends TestCase
{
    public function test_using_config_applies_defaults_and_skips_nulls(): void
    {
        config()->set('laranail.toolkit.runtime.defaults', [
            'memory_limit' => '321M',
            'max_execution_time' => null,
        ]);
        config()->set('laranail.toolkit.runtime.default_profile', null);
        config()->set('laranail.toolkit.runtime.ini', []);
        config()->set('laranail.toolkit.runtime.disable_tools', []);

        $pending = RuntimeConfigurator::fromConfig()->getPending();

        $this->assertSame('321M', $pending['memory_limit']);
        $this->assertArrayNotHasKey('max_execution_time', $pending);
    }

    public function test_named_profile_layers_over_defaults_and_disables_tools(): void
    {
        // The shipped `import` profile: 1G, 30-minute timeout, Telescope off.
        $config = RuntimeConfigurator::fromConfig('import');

        $this->assertSame('1G', $config->getPending()['memory_limit']);
        $this->assertSame(1800, $config->getPending()['max_execution_time']);
        $this->assertArrayHasKey('telescope', $config->getDisabledTools());
    }

    public function test_default_profile_is_used_when_none_passed(): void
    {
        config()->set('laranail.toolkit.runtime.default_profile', 'batch');

        $config = RuntimeConfigurator::fromConfig();

        $this->assertSame('2G', $config->getPending()['memory_limit']);
        $this->assertEqualsCanonicalizing(
            ['telescope', 'xdebug', 'clockwork', 'debugbar'],
            array_keys($config->getDisabledTools()),
        );
    }

    public function test_extra_ini_map_is_applied(): void
    {
        config()->set('laranail.toolkit.runtime.ini', ['session.gc_maxlifetime' => 7200]);

        $config = RuntimeConfigurator::fromConfig();

        $this->assertSame(7200, $config->getPending()['session.gc_maxlifetime']);
    }

    public function test_apply_on_boot_defaults_to_off(): void
    {
        // No global INI mutation happens at boot unless explicitly enabled.
        $this->assertFalse(config('laranail.toolkit.runtime.apply_on_boot'));
    }

    public function test_unknown_profile_logs_a_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'nonexistent'));

        RuntimeConfigurator::fromConfig('nonexistent');
    }
}
