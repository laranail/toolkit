<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Providers;

use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;
use Simtabi\Laranail\Toolkit\Commands\IdeHelperMacros;
use Simtabi\Laranail\Toolkit\Commands\MakeCrud;
use Simtabi\Laranail\Toolkit\Commands\Tidy;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiRequestMiddleware;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiResponseMiddleware;
use Simtabi\Laranail\Toolkit\Http\Middleware\EmailObfuscatorMiddleware;
use Simtabi\Laranail\Toolkit\Macros\MacroServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLog;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;
use Simtabi\Laranail\Toolkit\Services\AuthenticationContextService;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SchedulerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\ErrorStorageService;
use Simtabi\Laranail\Toolkit\Services\FileService;
use Simtabi\Laranail\Toolkit\Services\HttpConfigurationService;
use Simtabi\Laranail\Toolkit\Services\LogService;
use Simtabi\Laranail\Toolkit\Services\ModelService;
use Simtabi\Laranail\Toolkit\Services\RateLimiterService;
use Simtabi\Laranail\Toolkit\Services\RouteService;
use Simtabi\Laranail\Toolkit\Services\SchedulerService;
use Simtabi\Laranail\Toolkit\Services\SessionService;
use Simtabi\Laranail\Toolkit\Services\SettingsStore;
use Simtabi\Laranail\Toolkit\Services\SystemService;
use Simtabi\Laranail\Toolkit\Services\ValidationService;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;
use Simtabi\Laranail\Toolkit\Support\RequirementsDiagnostics;
use Simtabi\Laranail\Toolkit\ToolkitManager;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Simtabi\Laranail\Toolkit\Traits\FileProcessingTrait;

/**
 * Toolkit service provider, built on the laranail/package-tools
 * {@see PackageServiceProvider} lifecycle. {@see configurePackage()} declares the
 * package fully and declaratively — config files (merged + publishable under the
 * dotted `laranail.toolkit.*` namespace), views, translations, migrations,
 * commands, route middleware, child providers, a validation rule and an `about`
 * section. {@see packageRegistered()} wires the container bindings.
 */
class ToolkitServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('laranail/toolkit')
            // Config merged + published under the dotted namespace:
            //   toolkit          → config('laranail.toolkit.*')
            //   feature-toggles  → config('laranail.toolkit.feature-toggles.*')
            //   atlas / captcha  → config('laranail.toolkit.atlas|captcha.*')
            //   security         → config('laranail.toolkit.security.*'), read by SecurityData
            // (atlas/captcha are centralised here so they get the publish-override
            // bridge; their deferred modules only bind services.)
            ->hasConfigFile(['toolkit', 'feature-toggles', 'atlas', 'captcha', 'security'])
            ->hasViews('laranail-toolkit')
            ->hasTranslations()
            ->discoversMigrations()
            ->runsMigrations()   // load (register) the discovered migrations so `migrate` runs them
            ->hasCommands([MakeCrud::class, IdeHelperMacros::class, Tidy::class])
            // Opt-in middleware aliases (none pushed onto the global stack).
            ->registerMiddlewareAliases([
                'access.log' => AccessLogMiddleware::class,
                'api.request' => ApiRequestMiddleware::class,
                'api.response' => ApiResponseMiddleware::class,
                'email.obfuscate' => EmailObfuscatorMiddleware::class,
            ])
            // Macro coordinator + Blade directives (eager) and the deferred
            // feature modules.
            ->hasChildProviders([
                MacroServiceProvider::class,
                BladeServiceProvider::class,
                GravatarServiceProvider::class,
                AvatarServiceProvider::class,
                CaptchaServiceProvider::class,
                ArchiverServiceProvider::class,
                AtlasServiceProvider::class,
                LivewireServiceProvider::class,
                LLMServiceProvider::class,
            ])
            ->hasValidationRules([
                'reject_common_passwords' => [
                    RejectCommonPasswords::class,
                    'The :attribute contains a common password that is not allowed.',
                ],
            ])
            ->hasAboutSections([
                'Laranail Toolkit' => static fn (): array => (new RequirementsDiagnostics())->toAboutArray(),
            ])
            // CRUD stubs, consumed by the MakeCrud command when overridden.
            ->publishDirectory(__DIR__ . '/../../stubs', base_path('stubs/vendor/laranail-toolkit'), 'stubs');
    }

    public function packageRegistered(): void
    {
        $this->app->bind('AccessLog', AccessLog::class);

        // Foundation services (stateful — fresh instance per resolve so each
        // consuming object gets its own error/auth context).
        $this->app->bind(ErrorStorageServiceInterface::class, ErrorStorageService::class);
        $this->app->bind(AuthenticationContextServiceInterface::class, AuthenticationContextService::class);

        // Request-scoped route helpers (Router + Request are container-resolved).
        $this->app->bind(RouteServiceInterface::class, RouteService::class);

        // Session / query-string filter-key helpers. The stateful method writes
        // through the injected session store + cookie jar (no facades). Singleton
        // so a single instance fronts the session/cookie write path.
        $this->app->singleton(SessionServiceInterface::class, fn ($app): SessionService => new SessionService(
            $app->make('session.store'),
            $app->make('cookie'),
            $app->make('request'),
        ));

        // HTTP client config builder (seeded from laranail.toolkit.http.*).
        $this->app->bind(HttpConfigurationServiceInterface::class, HttpConfigurationService::class);

        // View-layer validation helpers (session + logger injected; HTML output
        // is e()-escaped). Folded from the legacy Foundation\ValidationService.
        $this->app->bind(ValidationServiceInterface::class, fn ($app): ValidationService => new ValidationService(
            $app->make('session.store'),
            $app->make(LoggerInterface::class),
        ));

        // File-domain service (primary, injectable; formerly static Helper::*).
        $this->app->singleton(FileServiceInterface::class, FileService::class);

        // System/runtime introspection service (delegates byte formatting to the
        // FileService so there is a single byte-formatter implementation).
        $this->app->singleton(SystemServiceInterface::class, fn ($app): SystemService => new SystemService(
            $app->make(FileServiceInterface::class),
        ));

        // Bind the Cache/Logger service contracts (interface→concrete service).
        $this->app->bind(CacheRepositoryInterface::class, CacheService::class);
        $this->app->bind(LoggerServiceInterface::class, LogService::class);

        // Settings store, rate limiter and scheduler service contracts.
        $this->app->bind(SettingsStoreInterface::class, SettingsStore::class);
        $this->app->bind(RateLimiterServiceInterface::class, RateLimiterService::class);
        $this->app->bind(SchedulerServiceInterface::class, SchedulerService::class);

        // Eloquent model helpers (no contract in the legacy surface).
        $this->app->bind(ModelService::class, fn ($app): ModelService => new ModelService(
            $app->make(LoggerInterface::class),
        ));

        $this->app->singleton('helper', fn () => new Helper());

        // Unified entry point to the feature modules (the `Toolkit` facade root).
        $this->app->singleton(ToolkitManager::class, fn ($app): ToolkitManager => new ToolkitManager($app));

        // Concrete-class binds for the relocated traits + stateful services so
        // `app(...)` keeps resolving them (parity with the legacy utilities).
        $this->loadClass(ApiResponseTrait::class);
        $this->loadClass(FileProcessingTrait::class);
        $this->loadServiceClasses([SettingsStore::class, SchedulerService::class, LogService::class]);
        $this->loadRateLimiterService();
        $this->loadCacheService();
    }

    /**
     * Dynamically bind the given class to a fresh instance.
     */
    private function loadClass(string $class): void
    {
        $this->app->bind($class, fn () => new $class());
    }

    /**
     * Bind the relocated service classes by their concrete class so `app(...)`
     * resolution is preserved. {@see LogService} stays a singleton (it is
     * injectable — let the container autowire its LogManager); the rest are
     * fresh-instance binds, matching the legacy utility wiring.
     *
     * @param list<class-string> $classes
     */
    private function loadServiceClasses(array $classes): void
    {
        foreach ($classes as $class) {
            if ($class === LogService::class) {
                $this->app->singleton($class);
            } else {
                $this->app->bind($class, fn () => new $class());
            }
        }
    }

    /**
     * Load the cache service with configured options.
     */
    private function loadCacheService(): void
    {
        $this->app->bind(CacheService::class, fn ($app): CacheService => new CacheService(
            ToolkitConfig::int('laranail.toolkit.cache.default_expiration'),
            ToolkitConfig::stringList('laranail.toolkit.cache.default_tags'),
            $app->make(LoggerInterface::class),
            ToolkitConfig::string('laranail.toolkit.cache.namespace'),
        ));
    }

    /**
     * Load the rate limiter service with dependency injection.
     */
    private function loadRateLimiterService(): void
    {
        $this->app->bind(RateLimiterService::class, fn ($app) => new RateLimiterService($app->make('cache.store')));
    }
}
