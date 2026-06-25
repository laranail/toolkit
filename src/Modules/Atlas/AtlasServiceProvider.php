<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Simtabi\Laranail\Toolkit\Utilities\CachingUtil;

/**
 * Deferred service provider for the self-contained Atlas module.
 *
 * Owns its own config merge/publish (under `laranail.toolkit.atlas`) so the
 * module can later be extracted into its own package without depending on the
 * root toolkit config.
 */
class AtlasServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        // The module owns its config namespace under `laranail.toolkit.atlas`.
        $this->mergeConfigFrom($this->configPath('atlas.php'), 'laranail.toolkit.atlas');
        // The Laravel-locale registry merges under `laranail.toolkit.languages`.
        $this->mergeConfigFrom($this->configPath('languages.php'), 'laranail.toolkit.languages');

        $this->app->singleton(AtlasService::class, static function (Application $app): AtlasService {
            /** @var Repository $config */
            $config = $app->make('config');

            $defaultLabel = Cast::toString($config->get('laranail.toolkit.atlas.default_label', 'name'), 'name');
            $cacheTtl = Cast::toInt($config->get('laranail.toolkit.atlas.cache_ttl', 1440), 1440);

            return new AtlasService(
                cache: $app->make(CachingUtil::class),
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

    public function boot(): void
    {
        $this->publishes([
            $this->configPath('atlas.php') => config_path('laranail-toolkit-atlas.php'),
            $this->configPath('languages.php') => config_path('laranail-toolkit-languages.php'),
        ], 'laranail-toolkit-atlas');
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

    /**
     * Absolute path to a config file in the package's config/ directory.
     *
     * Provider lives at src/Modules/Atlas/, so the package config/ dir is three
     * levels up.
     */
    private function configPath(string $file): string
    {
        return __DIR__ . '/../../../config/' . $file;
    }
}
