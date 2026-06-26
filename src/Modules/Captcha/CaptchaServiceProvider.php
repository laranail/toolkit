<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Deferred service provider for the self-contained Captcha module.
 *
 * The module config is merged + published centrally by ToolkitServiceProvider
 * under `laranail.toolkit.captcha.*` (so it shares the package-tools publish
 * override bridge); this provider only binds the module's services.
 */
class CaptchaServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->singleton(CaptchaService::class, static function (Application $app): CaptchaService {
            /** @var Repository $config */
            $config = $app->make('config');
            $default = Cast::toString($config->get('laranail.toolkit.captcha.default_provider', 'recaptcha'), 'recaptcha');

            return new CaptchaService($default);
        });

        // Resolve the contract to the default provider driver.
        $this->app->bind(
            CaptchaProviderInterface::class,
            static fn (Application $app): CaptchaProviderInterface => $app->make(CaptchaService::class)->getProvider(),
        );

        $this->app->alias(CaptchaService::class, 'laranail.captcha');
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
}
