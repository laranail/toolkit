<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Contracts\Config\Repository;
use Simtabi\Laranail\Toolkit\Exceptions\ConfigException;
use Simtabi\Laranail\Toolkit\Services\ConfigManager;
use Simtabi\Laranail\Toolkit\Services\Contracts\ConfigManagerInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ConfigManagerTest extends TestCase
{
    private function manager(): ConfigManager
    {
        return new ConfigManager($this->app->make(Repository::class), $this->app);
    }

    public function test_resolves_from_the_container_by_contract(): void
    {
        $this->assertInstanceOf(ConfigManager::class, $this->app->make(ConfigManagerInterface::class));
    }

    public function test_set_and_get_round_trip(): void
    {
        $config = $this->manager();
        $config->set('demo.name', 'toolkit');

        $this->assertSame('toolkit', $config->get('demo.name'));
        $this->assertTrue($config->has('demo.name'));
    }

    public function test_merge_deep_merges_without_duplicating_scalars(): void
    {
        $config = $this->manager();
        $config->set('demo.opts', ['a' => 1, 'nested' => ['x' => 1]]);

        // array_merge_recursive would turn a=>1 + a=>2 into a=>[1,2]; deepMerge
        // must overwrite the scalar and recurse the nested array.
        $config->merge('demo.opts', ['a' => 2, 'nested' => ['y' => 2]]);

        $this->assertSame(
            ['a' => 2, 'nested' => ['x' => 1, 'y' => 2]],
            $config->get('demo.opts'),
        );
    }

    public function test_remove_deletes_a_nested_key(): void
    {
        $config = $this->manager();
        $config->set('demo.keep', 'yes');
        $config->set('demo.drop', 'no');

        $config->remove('demo.drop');

        $this->assertFalse($config->has('demo.drop'));
        $this->assertSame('yes', $config->get('demo.keep'));
    }

    public function test_conditional_helpers(): void
    {
        $config = $this->manager();

        $config
            ->when(true, fn (ConfigManager $c) => $c->set('demo.when', 'ran'))
            ->unless(false, fn (ConfigManager $c) => $c->set('demo.unless', 'ran'))
            ->when(false, fn (ConfigManager $c) => $c->set('demo.skip', 'nope'));

        $this->assertSame('ran', $config->get('demo.when'));
        $this->assertSame('ran', $config->get('demo.unless'));
        $this->assertNull($config->get('demo.skip'));
    }

    public function test_when_has_passes_current_value(): void
    {
        $config = $this->manager();
        $config->set('demo.present', 41);

        $seen = null;
        $config->whenHas('demo.present', function (ConfigManager $c, mixed $value) use (&$seen): void {
            $seen = $value;
        });

        $this->assertSame(41, $seen);
    }

    public function test_load_and_override_throws_when_file_is_missing(): void
    {
        $this->expectException(ConfigException::class);

        $this->manager()->loadAndOverride('demo', '/does/not/exist.php');
    }

    public function test_logging_records_a_null_value(): void
    {
        $config = $this->manager()->withLogging();
        $config->set('demo.nullable', null);

        $log = $config->getLog();

        $this->assertCount(1, $log);
        $this->assertSame('set', $log[0]['operation']);
        $this->assertArrayHasKey('value', $log[0]);
        $this->assertNull($log[0]['value']);
    }

    public function test_no_debug_leak_methods(): void
    {
        $this->assertFalse(method_exists(ConfigManager::class, 'dump'));
        $this->assertFalse(method_exists(ConfigManager::class, 'dd'));
    }
}
