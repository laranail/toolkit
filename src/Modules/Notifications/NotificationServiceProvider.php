<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;

/**
 * Deferred service provider for the self-contained Notifications module.
 *
 * Owns its own config merge/publish under `laranail.toolkit.notifications` so
 * the module can later be extracted into its own package without depending on
 * the root toolkit config.
 */
class NotificationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        // The module owns its config namespace under `laranail.toolkit.notifications`.
        $this->mergeConfigFrom($this->configPath(), 'laranail.toolkit.notifications');

        $this->app->singleton(NotificationService::class, static function (Application $app): NotificationService {
            /** @var Repository $config */
            $config = $app->make('config');

            /** @var array<string, mixed> $settings */
            $settings = (array) $config->get('laranail.toolkit.notifications', []);

            $logger = $app->bound(LoggerInterface::class) ? $app->make(LoggerInterface::class) : null;

            return new NotificationService($settings, $logger);
        });

        $this->app->alias(NotificationService::class, 'laranail.notifications');
    }

    public function boot(): void
    {
        $this->publishes([
            $this->configPath() => config_path('laranail-toolkit-notifications.php'),
        ], 'laranail-toolkit-notifications');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            NotificationService::class,
            'laranail.notifications',
        ];
    }

    /**
     * Absolute path to the package's notifications config file.
     *
     * Provider lives at src/Modules/Notifications/, so the package config/ dir
     * is three levels up.
     */
    private function configPath(): string
    {
        return __DIR__ . '/../../../config/notifications.php';
    }
}
