<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * Deferred service provider for the self-contained Captcha module.
 *
 * Owns its own config merge/publish so the module can later be extracted into
 * its own package without depending on the root toolkit config.
 */
class CaptchaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        // The module owns its config namespace under `laranail.toolkit.captcha`.
        $this->mergeConfigFrom($this->configPath(), 'laranail.toolkit.captcha');

        $this->app->singleton(CaptchaService::class, static function (Application $app): CaptchaService {
            /** @var Repository $config */
            $config = $app->make('config');
            $default = (string) $config->get('laranail.toolkit.captcha.default_provider', 'recaptcha');

            return new CaptchaService($default);
        });

        // Resolve the contract to the default provider driver.
        $this->app->bind(
            CaptchaProviderInterface::class,
            static fn (Application $app): CaptchaProviderInterface => $app->make(CaptchaService::class)->getProvider(),
        );

        $this->app->alias(CaptchaService::class, 'laranail.captcha');
    }

    public function boot(): void
    {
        $this->publishes([
            $this->configPath() => config_path('laranail-toolkit-captcha.php'),
        ], 'laranail-toolkit-captcha');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            CaptchaService::class,
            CaptchaProviderInterface::class,
            'laranail.captcha',
        ];
    }

    /**
     * Absolute path to the package's captcha config file.
     *
     * Provider lives at src/Modules/Captcha/, so the package config/ dir is
     * three levels up.
     */
    private function configPath(): string
    {
        return __DIR__ . '/../../../config/captcha.php';
    }
}
