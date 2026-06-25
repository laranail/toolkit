<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Commands\DatabaseManager;
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
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ImportDatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RateLimiterServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SchedulerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SettingsStoreInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\DatabaseService;
use Simtabi\Laranail\Toolkit\Services\ErrorStorageService;
use Simtabi\Laranail\Toolkit\Services\FileService;
use Simtabi\Laranail\Toolkit\Services\HttpConfigurationService;
use Simtabi\Laranail\Toolkit\Services\ImportDatabaseService;
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

class ToolkitServiceProvider extends ServiceProvider
{
    /**
     * Feature-module providers. Each is deferred and self-contained so modules
     * can later be extracted into their own packages.
     *
     * @var list<class-string<ServiceProvider>>
     */
    private const MODULE_PROVIDERS = [
        GravatarServiceProvider::class,
        AvatarServiceProvider::class,
        CaptchaServiceProvider::class,
        ArchiverServiceProvider::class,
        AtlasServiceProvider::class,
        LivewireServiceProvider::class,
        LLMServiceProvider::class,
    ];

    public function register(): void
    {
        foreach (self::MODULE_PROVIDERS as $provider) {
            $this->app->register($provider);
        }

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

        // Database helpers + maintenance, confined to the application base path.
        $this->app->bind(DatabaseServiceInterface::class, fn ($app): DatabaseService => new DatabaseService(
            $app->make(LoggerInterface::class),
            $app->make('session.store'),
            $app->basePath(),
        ));

        // File-domain service (primary, injectable; formerly static Helper::*).
        $this->app->singleton(FileServiceInterface::class, FileService::class);

        // System/runtime introspection service (delegates byte formatting to the
        // FileService so there is a single byte-formatter implementation).
        $this->app->singleton(SystemServiceInterface::class, fn ($app): SystemService => new SystemService(
            $app->make(FileServiceInterface::class),
        ));

        // Generic, safe SQL importer (path-guarded, transactional, no credential
        // logging).
        $this->app->bind(ImportDatabaseServiceInterface::class, fn ($app): ImportDatabaseService => new ImportDatabaseService(
            $app->make('db'),
            $app->make(LoggerInterface::class),
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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the macro coordinator eagerly so all grouped macros load
        // globally (macro registration must not be deferred).
        $this->app->register(MacroServiceProvider::class);

        // Register custom Blade directives eagerly (directive registration
        // must not be deferred).
        $this->app->register(BladeServiceProvider::class);

        // Surface toolkit runtime diagnostics under `php artisan about`.
        $this->registerAboutDiagnostics();

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Load + publish views and translations (namespace: laranail-toolkit).
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'laranail-toolkit');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/assets/lang', 'laranail-toolkit');

        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/laranail-toolkit'),
        ], 'laranail-toolkit-views');

        $this->publishes([
            __DIR__ . '/../../resources/assets/lang' => lang_path('vendor/laranail-toolkit'),
        ], 'laranail-toolkit-lang');

        // Publish configs
        $this->publishes([
            __DIR__ . '/../../config/toolkit.php' => config_path('laranail-toolkit.php'),
        ], 'laranail-toolkit-config');

        $this->publishes([
            __DIR__ . '/../../config/feature-toggles.php' => config_path('feature-toggles.php'),
        ], 'laranail-toolkit-feature-toggles');

        // Publish the merged security data file (passwords + passphrase
        // wordlist + redaction keys). SecurityData prefers this override when
        // present; otherwise it loads the package default.
        $this->publishes([
            __DIR__ . '/../../config/security.php' => config_path('laranail-toolkit-security.php'),
        ], 'laranail-toolkit-security');

        $this->mergeConfigFrom(__DIR__ . '/../../config/toolkit.php', 'laranail.toolkit');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'laranail-toolkit-migrations');

        // Publish the AccessLog model (now part of the Security module)
        $this->publishes([
            __DIR__ . '/../Modules/Security/AccessLog/AccessLog.php' => app_path('Models/AccessLog.php'),
        ], 'laranail-toolkit-models');

        // Publish traits
        $this->publishes([
            __DIR__ . '/../Traits/ApiResponseTrait.php' => app_path('Traits/ApiResponseTrait.php'),
        ], 'laranail-toolkit-api-response-trait');

        // Publish validation rules
        $this->publishValidationRule();

        $this->loadClass(ApiResponseTrait::class);
        $this->loadClass(FileProcessingTrait::class);

        // Publish the relocated Services / Support classes (formerly Utilities).
        $this->publishComponent('Services/CacheService', 'cache');
        $this->publishComponent('Services/SettingsStore', 'settings');
        $this->publishComponent('Services/SchedulerService', 'scheduler');
        $this->publishComponent('Support/QueryParameters', 'query-parameters');
        $this->publishComponent('Services/RateLimiterService', 'rate-limiter');
        $this->publishComponent('Support/Pagination', 'pagination');
        $this->publishComponent('Support/CollectionFilter', 'collection-filter');
        $this->publishComponent('Services/LogService', 'log');
        $this->publishComponent('Support/Environment', 'environment');
        $this->publishComponent('Support/AuthHelper', 'auth');

        // Bind the relocated stateful services by their concrete class so
        // `app(...)` keeps resolving them (parity with the legacy utilities).
        $this->loadServiceClasses([
            SettingsStore::class,
            SchedulerService::class,
            LogService::class,
        ]);
        $this->loadRateLimiterService();
        $this->loadCacheService();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([MakeCrud::class, IdeHelperMacros::class, DatabaseManager::class, Tidy::class]);

            $this->publishes([
                __DIR__ . '/../../stubs' => base_path('stubs/vendor/laranail-toolkit'),
            ], 'laranail-toolkit-stubs');
        }

        // Register middleware. All are opt-in (attach per route/group) — none is
        // pushed onto the global stack. `api.request` snake_cases incoming keys;
        // `api.response` envelopes + camelCases outgoing JSON.
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('access.log', AccessLogMiddleware::class);
        $router->aliasMiddleware('api.request', ApiRequestMiddleware::class);
        $router->aliasMiddleware('api.response', ApiResponseMiddleware::class);
        // `email.obfuscate` HTML-entity-encodes email addresses in HTML responses
        // (JSON is left untouched) — opt-in per route/group.
        $router->aliasMiddleware('email.obfuscate', EmailObfuscatorMiddleware::class);

        // Register custom validation rules
        $this->registerValidationRules();
    }

    /**
     * Surface the toolkit's requirements diagnostics under `php artisan about`.
     */
    private function registerAboutDiagnostics(): void
    {
        if (!class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Laranail Toolkit', static fn (): array => new RequirementsDiagnostics()->toAboutArray());
    }

    /**
     * Dynamically load the given class.
     */
    private function loadClass(string $class)
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
    private function loadServiceClasses(array $classes)
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
    private function loadCacheService()
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
    private function loadRateLimiterService()
    {
        $this->app->bind(RateLimiterService::class, fn ($app) => new RateLimiterService($app->make('cache.store')));
    }

    private function publishComponent(string $relativePath, string $name)
    {
        $this->publishes([
            __DIR__ . '/../' . $relativePath . '.php' => app_path($relativePath . '.php'),
        ], 'laranail-toolkit-' . $name);
    }

    /**
     * Register custom validation rules.
     */
    private function registerValidationRules(): void
    {
        Validator::extend('reject_common_passwords', function ($attribute, $value, $parameters, $validator) {
            $rule = new RejectCommonPasswords();
            $failed = false;
            $rule->validate($attribute, $value, function ($message) use (&$failed) {
                $failed = true;
            });

            return !$failed;
        }, 'The :attribute contains a common password that is not allowed.');

        Validator::replacer('reject_common_passwords', fn ($message, $attribute, $rule, $parameters) => str_replace(':attribute', $attribute, $message));
    }

    /**
     * Publish validation rules with correct namespace for app directory.
     */
    private function publishValidationRule(): void
    {
        $this->publishes([
            __DIR__ . '/../Rules/RejectCommonPasswords.php' => app_path('Rules/RejectCommonPasswords.php'),
        ], 'laranail-toolkit-validation-rules');
    }
}
