<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Atlas;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('atlas')]
class AtlasServiceProviderTest extends TestCase
{
    public function test_provides_lists_the_deferred_bindings(): void
    {
        $provider = new AtlasServiceProvider($this->app);

        $this->assertSame([
            AtlasService::class,
            AtlasServiceInterface::class,
            'laranail.atlas',
        ], $provider->provides());
    }

    public function test_config_is_merged_under_the_module_namespace(): void
    {
        $this->assertSame('name', config('laranail.toolkit.atlas.default_label'));
        $this->assertSame(1440, (int) config('laranail.toolkit.atlas.cache_ttl'));
    }

    public function test_config_publish_group_is_registered(): void
    {
        $groups = AtlasServiceProvider::pathsToPublish(AtlasServiceProvider::class, 'laranail-toolkit-atlas');

        $this->assertNotEmpty($groups);
        $target = config_path('laranail-toolkit-atlas.php');
        $this->assertContains($target, array_values($groups));
    }

    public function test_default_label_config_drives_for_select_box(): void
    {
        config()->set('laranail.toolkit.atlas.default_label', 'official_name');
        // Re-resolve so the singleton picks up the new config.
        $this->app->forgetInstance(AtlasService::class);

        $service = $this->app->make(AtlasServiceInterface::class);
        $box = $service->forSelectBox('bogus_label');

        $this->assertSame('United States of America', $box['US']);
    }
}
