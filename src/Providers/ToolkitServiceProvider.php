<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Providers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
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
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Listeners\LogCacheEvents;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLog;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;
use Simtabi\Laranail\Toolkit\Services\AuthenticationContextService;
use Simtabi\Laranail\Toolkit\Services\CacheService;
use Simtabi\Laranail\Toolkit\Services\ConfigManager;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ConfigManagerInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\LoggerServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\PythonApiServiceInterface;
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
use Simtabi\Laranail\Toolkit\Services\PythonApiService;
use Simtabi\Laranail\Toolkit\Services\RateLimiterService;
use Simtabi\Laranail\Toolkit\Services\RouteService;
use Simtabi\Laranail\Toolkit\Services\SchedulerService;
use Simtabi\Laranail\Toolkit\Services\SessionService;
use Simtabi\Laranail\Toolkit\Services\SettingsStore;
use Simtabi\Laranail\Toolkit\Services\SystemService;
use Simtabi\Laranail\Toolkit\Services\ValidationService;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;
use Simtabi\Laranail\Toolkit\Support\RequirementsDiagnostics;
use Simtabi\Laranail\Toolkit\Support\RuntimeConfigurator;
use Simtabi\Laranail\Toolkit\ToolkitManager;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Simtabi\Laranail\Toolkit\Traits\FileProcessingTrait;

/**
 * The toolkit's single, self-contained service provider.
 *
 * Built on plain Laravel `ServiceProvider` primitives — the package is
 * **independent of laranail/package-tools** (it is the foundational library
 * other laranail packages build on). `register()` merges the config files under
 * the dotted `laranail.toolkit.*` namespace (with a published-override bridge),
 * registers the eager + deferred child providers, and wires the container
 * bindings. `boot()` loads views/translations/migrations, registers the
 * commands, route-middleware aliases, the `reject_common_passwords` validator,
 * the `php artisan about` section, the cache-events listener, and (in console)
 * the publish groups.
 */
class ToolkitServiceProvider extends ServiceProvider
{
    /**
     * Config files merged/published under the dotted namespace. The default
     * file (`toolkit`) mounts at `laranail.toolkit`; every other file mounts at
     * `laranail.toolkit.<file>`.
     *
     * @var list<string>
     */
    private const array CONFIG_FILES = ['toolkit', 'feature-toggles', 'atlas', 'security'];

    /**
     * Eager coordinators (macros, Blade directives) + the deferred feature
     * module providers.
     *
     * @var list<class-string>
     */
    private const array CHILD_PROVIDERS = [
        MacroServiceProvider::class,
        BladeServiceProvider::class,
        GravatarServiceProvider::class,
        AvatarServiceProvider::class,
        ArchiverServiceProvider::class,
        AtlasServiceProvider::class,
        LivewireServiceProvider::class,
        LLMServiceProvider::class,
    ];

    /**
     * Opt-in route-middleware aliases (none pushed onto the global stack).
     *
     * @var array<string, class-string>
     */
    private const array MIDDLEWARE_ALIASES = [
        'access.log' => AccessLogMiddleware::class,
        'api.request' => ApiRequestMiddleware::class,
        'api.response' => ApiResponseMiddleware::class,
        'email.obfuscate' => EmailObfuscatorMiddleware::class,
    ];

    public function register(): void
    {
        $root = $this->packageRoot();

        // 1. Merge each config file under its dotted key, then apply the
        //    published-override bridge so an edited `config/laranail/…` file
        //    still reaches the dotted key (deep merge; wins over defaults).
        foreach (self::CONFIG_FILES as $file) {
            $key = $this->configKey($file);
            $this->mergeConfigFrom("{$root}/config/{$file}.php", $key);
            $this->mergePublishedConfigOverride($key);
        }

        // 2. Register the child providers eagerly (deferred ones keep their own
        //    deferral semantics; this only forces registration timing to match).
        foreach (self::CHILD_PROVIDERS as $provider) {
            $this->app->register($provider);
        }

        // 3. Container bindings.
        $this->registerBindings();
    }

    public function boot(): void
    {
        $root = $this->packageRoot();

        $this->loadViewsFrom("{$root}/resources/views", 'laranail-toolkit');
        $this->loadTranslationsFrom("{$root}/resources/lang", 'laranail/toolkit');
        $this->loadJsonTranslationsFrom("{$root}/resources/lang");
        // Published JSON string-translation overrides in the app lang path.
        $this->loadJsonTranslationsFrom($this->app->langPath('vendor/laranail/toolkit'));
        $this->loadMigrationsFrom("{$root}/database/migrations");

        $this->commands([MakeCrud::class, IdeHelperMacros::class, Tidy::class]);

        $router = $this->app->make(Router::class);
        foreach (self::MIDDLEWARE_ALIASES as $alias => $class) {
            $router->aliasMiddleware($alias, $class);
        }

        $this->registerValidationRules();
        $this->registerAboutSection();

        // Log the cache lifecycle when opted in (config-gated inside the listener).
        Event::listen(CacheEvents::class, [LogCacheEvents::class, 'handle']);

        // Optionally apply the configured runtime/INI profile at boot (opt-in;
        // mutates PHP INI for every request/command).
        if (ToolkitConfig::bool('laranail.toolkit.runtime.apply_on_boot')) {
            RuntimeConfigurator::fromConfig()->apply();
        }

        if ($this->app->runningInConsole()) {
            $this->registerPublishing($root);
        }
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    private function registerBindings(): void
    {
        $this->app->bind('AccessLog', AccessLog::class);

        // Foundation services (stateful — fresh instance per resolve so each
        // consuming object gets its own error/auth context).
        $this->app->bind(ErrorStorageServiceInterface::class, ErrorStorageService::class);
        $this->app->bind(AuthenticationContextServiceInterface::class, AuthenticationContextService::class);

        // Request-scoped route helpers (Router + Request are container-resolved).
        $this->app->bind(RouteServiceInterface::class, RouteService::class);

        // Session / query-string filter-key helpers. Singleton so a single
        // instance fronts the session/cookie write path.
        $this->app->singleton(SessionServiceInterface::class, fn ($app): SessionService => new SessionService(
            $app->make('session.store'),
            $app->make('cookie'),
            $app->make('request'),
        ));

        // HTTP client config builder (seeded from laranail.toolkit.http.*).
        $this->app->bind(HttpConfigurationServiceInterface::class, HttpConfigurationService::class);

        // View-layer validation helpers (session + logger injected; HTML output
        // is e()-escaped).
        $this->app->bind(ValidationServiceInterface::class, fn ($app): ValidationService => new ValidationService(
            $app->make('session.store'),
            $app->make(LoggerInterface::class),
        ));

        // File-domain service (primary, injectable).
        $this->app->singleton(FileServiceInterface::class, FileService::class);

        // System/runtime introspection (delegates byte formatting to FileService).
        $this->app->singleton(SystemServiceInterface::class, fn ($app): SystemService => new SystemService(
            $app->make(FileServiceInterface::class),
        ));

        // Cache/Logger service contracts (interface→concrete service).
        $this->app->bind(CacheRepositoryInterface::class, CacheService::class);
        $this->app->bind(LoggerServiceInterface::class, LogService::class);

        // Fluent runtime config manager (stateful — fresh per resolve).
        $this->app->bind(ConfigManagerInterface::class, fn ($app): ConfigManager => new ConfigManager(
            $app->make(ConfigRepository::class),
            $app,
        ));

        // Config-driven Python/external microservice HTTP client factory.
        $this->app->bind(PythonApiServiceInterface::class, fn ($app): PythonApiService => new PythonApiService(
            $app->make(ConfigRepository::class),
            $app->make(HttpConfigurationServiceInterface::class),
            $app->make(LoggerInterface::class),
        ));

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
        // `app(...)` keeps resolving them.
        $this->loadClass(ApiResponseTrait::class);
        $this->loadClass(FileProcessingTrait::class);
        $this->loadServiceClasses([SettingsStore::class, SchedulerService::class, LogService::class]);
        $this->loadRateLimiterService();
        $this->loadCacheService();
    }

    // -----------------------------------------------------------------------
    // Boot helpers
    // -----------------------------------------------------------------------

    private function registerValidationRules(): void
    {
        Validator::extend(
            'reject_common_passwords',
            static fn (string $attribute, mixed $value): bool => Validator::make(
                [$attribute => $value],
                [$attribute => [new RejectCommonPasswords()]],
            )->passes(),
            'The :attribute contains a common password that is not allowed.',
        );

        Validator::replacer(
            'reject_common_passwords',
            static fn (string $message, string $attribute): string => str_replace(':attribute', $attribute, $message),
        );
    }

    private function registerAboutSection(): void
    {
        if (!class_exists(AboutCommand::class)) {
            return;
        }

        AboutCommand::add('Laranail Toolkit', static fn (): array => (new RequirementsDiagnostics())->toAboutArray());
    }

    private function registerPublishing(string $root): void
    {
        $configPublishes = [];
        foreach (self::CONFIG_FILES as $file) {
            $dest = config_path(str_replace('.', '/', $this->configKey($file)) . '.php');
            $configPublishes["{$root}/config/{$file}.php"] = $dest;
        }
        $this->publishes($configPublishes, 'laranail::toolkit-config');

        $this->publishes(
            ["{$root}/resources/views" => base_path('resources/views/vendor/laranail-toolkit')],
            'laranail::toolkit-views',
        );

        $this->publishes(
            ["{$root}/resources/lang" => $this->app->langPath('vendor/laranail/toolkit')],
            'laranail::toolkit-translations',
        );

        $this->publishesMigrations(
            ["{$root}/database/migrations" => database_path('migrations')],
            'laranail::toolkit-migrations',
        );

        $this->publishes(
            ["{$root}/stubs" => base_path('stubs/vendor/laranail-toolkit')],
            'laranail::toolkit-stubs',
        );
    }

    // -----------------------------------------------------------------------
    // Config merge internals
    // -----------------------------------------------------------------------

    /**
     * Dotted config key for a package config file: the default `toolkit` file
     * mounts at the bare namespace; every other file mounts beneath it.
     */
    private function configKey(string $file): string
    {
        return $file === 'toolkit' ? 'laranail.toolkit' : "laranail.toolkit.{$file}";
    }

    /**
     * Deep-merge a published `config/laranail/…` override over the merged
     * defaults at the dotted key (no-op when config is cached or absent).
     */
    private function mergePublishedConfigOverride(string $key): void
    {
        if (App::configurationIsCached()) {
            return;
        }

        $published = config_path(str_replace('.', '/', $key) . '.php');
        if (!is_file($published)) {
            return;
        }

        $override = require $published;
        if (!is_array($override)) {
            return;
        }

        $config = $this->app->make(ConfigRepository::class);
        /** @var array<string, mixed> $current */
        $current = (array) $config->get($key, []);
        $config->set($key, array_replace_recursive($current, $override));
    }

    // -----------------------------------------------------------------------
    // Binding helpers
    // -----------------------------------------------------------------------

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
     * fresh-instance binds.
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

    /**
     * The package root (two levels above `src/Providers`).
     */
    private function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}
