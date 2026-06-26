<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Deferred service provider for the self-contained Atlas module.
 *
 * The module config is merged + published centrally by ToolkitServiceProvider
 * under `laranail.toolkit.atlas.*` (so it shares the package-tools publish
 * override bridge); this provider only binds the module's services.
 */
class AtlasServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(AtlasService::class, static function (Application $app): AtlasService {
            /** @var Repository $config */
            $config = $app->make('config');

            $defaultLabel = Cast::toString($config->get('laranail.toolkit.atlas.default_label', 'name'), 'name');
            $cacheTtl = Cast::toInt($config->get('laranail.toolkit.atlas.cache_ttl', 1440), 1440);

            return new AtlasService(
                cache: $app->make(CacheService::class),
                config: $config,
                defaultLabel: $defaultLabel,
                cacheTtl: $cacheTtl,
            );
        });

        $this->app->bind(
            AtlasServiceInterface::class,
            static fn (Application $app): AtlasServiceInterface => $app->make(AtlasService::class),
        );

        $this->app->alias(AtlasService::class, 'laranail.atlas');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            AtlasService::class,
            AtlasServiceInterface::class,
            'laranail.atlas',
        ];
    }
}
