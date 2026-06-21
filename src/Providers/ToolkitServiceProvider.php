<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Providers;

use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Commands\MakeCrud;
use Simtabi\Laranail\Toolkit\Helpers\XHelper;
use Simtabi\Laranail\Toolkit\LLMProviders\Claude\ClaudeProvider;
use Simtabi\Laranail\Toolkit\LLMProviders\Contracts\LLMProviderInterface;
use Simtabi\Laranail\Toolkit\LLMProviders\Gemini\GeminiProvider;
use Simtabi\Laranail\Toolkit\LLMProviders\OpenAI\OpenAIProvider;
use Simtabi\Laranail\Toolkit\Macros\MacroServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\AccessLog\Http\Middleware\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Modules\AccessLog\Models\AccessLog;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceProvider;
use Simtabi\Laranail\Toolkit\Rules\RejectCommonPasswords;
use Simtabi\Laranail\Toolkit\Support\Diagnostics\RequirementsDiagnostics;
use Simtabi\Laranail\Toolkit\Traits\ApiResponseTrait;
use Simtabi\Laranail\Toolkit\Traits\FileProcessingTrait;
use Simtabi\Laranail\Toolkit\Utilities\CachingUtil;
use Simtabi\Laranail\Toolkit\Utilities\ConfigUtil;
use Simtabi\Laranail\Toolkit\Utilities\FeatureToggleUtil;
use Simtabi\Laranail\Toolkit\Utilities\FilteringUtil;
use Simtabi\Laranail\Toolkit\Utilities\LoggingUtil;
use Simtabi\Laranail\Toolkit\Utilities\PaginationUtil;
use Simtabi\Laranail\Toolkit\Utilities\QueryParameterUtil;
use Simtabi\Laranail\Toolkit\Utilities\RateLimiterUtil;
use Simtabi\Laranail\Toolkit\Utilities\SchedulerUtil;

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
    ];

    public function register(): void
    {
        foreach (self::MODULE_PROVIDERS as $provider) {
            $this->app->register($provider);
        }

        $this->app->bind('AccessLog', AccessLog::class);

        // Register base LLM Provider interface with provider selection
        $this->app->bind(LLMProviderInterface::class, function ($app) {
            $default = config('laranail.toolkit.llm.default_provider', 'openai');

            if ($default === 'gemini') {
                return new GeminiProvider(
                    apiKey: config('laranail.toolkit.gemini.api_key'),
                    maxRetries: (int) config('laranail.toolkit.gemini.max_retries', 3),
                    retryDelay: (int) config('laranail.toolkit.gemini.retry_delay', 2),
                    baseUrl: (string) config('laranail.toolkit.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta')
                );
            }

            if ($default === 'claude') {
                return new ClaudeProvider(
                    apiKey: config('laranail.toolkit.claude.api_key'),
                    maxRetries: (int) config('laranail.toolkit.claude.max_retries', 3),
                    retryDelay: (int) config('laranail.toolkit.claude.retry_delay', 2),
                    baseUrl: (string) config('laranail.toolkit.claude.base_url', 'https://api.anthropic.com')
                );
            }

            return new OpenAIProvider(
                apiKey: config('laranail.toolkit.openai.api_key'),
                maxRetries: (int) config('laranail.toolkit.openai.max_retries', 3),
                retryDelay: (int) config('laranail.toolkit.openai.retry_delay', 2)
            );
        });

        $this->app->singleton('xhelper', function () {
            return new XHelper();
        });
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

        $this->mergeConfigFrom(__DIR__ . '/../../config/toolkit.php', 'laranail.toolkit');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations' => database_path('migrations'),
        ], 'laranail-toolkit-migrations');

        // Publish models
        $this->publishes([
            __DIR__ . '/../Models' => app_path('Models'),
        ], 'laranail-toolkit-models');

        // Publish traits
        $this->publishes([
            __DIR__ . '/../Traits/ApiResponseTrait.php' => app_path('Traits/ApiResponseTrait.php'),
        ], 'laranail-toolkit-api-response-trait');

        // Publish validation rules
        $this->publishValidationRule();

        $this->loadClass(ApiResponseTrait::class);
        $this->loadClass(FileProcessingTrait::class);

        // Publish utilities
        $this->publishUtility('CachingUtil', 'caching');
        $this->publishUtility('ConfigUtil', 'config-util');
        $this->publishUtility('SchedulerUtil', 'scheduler');
        $this->publishUtility('QueryParameterUtil', 'query-parameter');
        $this->publishUtility('RateLimiterUtil', 'rate-limiter');
        $this->publishUtility('PaginationUtil', 'paginator');
        $this->publishUtility('FilteringUtil', 'filtering');
        $this->publishUtility('LoggingUtil', 'logging');

        // Load utilities
        $classes = [
            ConfigUtil::class,
            SchedulerUtil::class,
            QueryParameterUtil::class,
            RateLimiterUtil::class,
            PaginationUtil::class,
            FilteringUtil::class,
            FeatureToggleUtil::class,
            LoggingUtil::class,
        ];

        $this->loadUtilityClasses($classes);
        $this->loadCachingUtility();

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([MakeCrud::class]);

            $this->publishes([
                __DIR__ . '/../../stubs' => base_path('stubs/vendor/laranail-toolkit'),
            ], 'laranail-toolkit-stubs');
        }

        // Register middleware
        $this->app['router']->aliasMiddleware('access.log', AccessLogMiddleware::class);

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

        AboutCommand::add('Laranail Toolkit', static fn (): array => (new RequirementsDiagnostics())->toAboutArray());
    }

    /**
     * Dynamically load the given class.
     */
    private function loadClass(string $class)
    {
        $this->app->bind($class, function () use ($class) {
            return new $class();
        });
    }

    /**
     * Dynamically load the given utility classes.
     */
    private function loadUtilityClasses(array $classes)
    {
        foreach ($classes as $class) {
            if ($class === RateLimiterUtil::class) {
                $this->loadRateLimiterUtility();
            } else {
                $this->app->bind($class, function () use ($class) {
                    return new $class();
                });
            }
        }
    }

    /**
     * Load the caching utility with configured options.
     */
    private function loadCachingUtility()
    {
        $config = config('laranail.toolkit.cache');

        $this->app->bind(CachingUtil::class, function () use ($config) {
            return new CachingUtil($config['default_expiration'], $config['default_tags']);
        });
    }

    /**
     * Load the rate limiter utility with dependency injection.
     */
    private function loadRateLimiterUtility()
    {
        $this->app->bind(RateLimiterUtil::class, function ($app) {
            return new RateLimiterUtil($app->make('cache.store'));
        });
    }

    private function publishUtility(string $utility, string $name)
    {
        $this->publishes([
            __DIR__ . '/../Utilities/' . $utility . '.php' => app_path('Utilities/' . $utility . '.php'),
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

        Validator::replacer('reject_common_passwords', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':attribute', $attribute, $message);
        });
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
