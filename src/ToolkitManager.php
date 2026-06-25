<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit;

use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Security\Passphrase;
use Simtabi\Laranail\Toolkit\Modules\Security\Password;
use Simtabi\Laranail\Toolkit\Modules\Security\Token;
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationContextServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
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
use Simtabi\Laranail\Toolkit\Services\ModelService;

/**
 * Unified, typed entry point to the toolkit's feature modules.
 *
 * Replaces the legacy 48-method `Laranail` service-locator with a small fluent
 * object: resolve a module's service through the container (deferred providers
 * boot on demand) and chain from there — e.g. `Toolkit::avatar()->setName(...)`.
 */
class ToolkitManager
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function avatar(): AvatarServiceInterface
    {
        return $this->app->make(AvatarServiceInterface::class);
    }

    public function gravatar(): GravatarServiceInterface
    {
        return $this->app->make(GravatarServiceInterface::class);
    }

    public function captcha(): CaptchaService
    {
        return $this->app->make(CaptchaService::class);
    }

    public function archiver(): ArchiverServiceInterface
    {
        return $this->app->make(ArchiverServiceInterface::class);
    }

    /**
     * Request-scoped route helpers (current name, parameters, active checks).
     */
    public function route(): RouteServiceInterface
    {
        return $this->app->make(RouteServiceInterface::class);
    }

    /**
     * View-layer validation helpers (e()-escaped error/old-input output).
     */
    public function validation(): ValidationServiceInterface
    {
        return $this->app->make(ValidationServiceInterface::class);
    }

    /**
     * Session / query-string filter-key helpers (stateful cookie/session write).
     */
    public function session(): SessionServiceInterface
    {
        return $this->app->make(SessionServiceInterface::class);
    }

    /**
     * File-name / size inspection plus path-guarded, exception-safe filesystem
     * probes (the primary, injectable file domain).
     */
    public function file(): FileServiceInterface
    {
        return $this->app->make(FileServiceInterface::class);
    }

    /**
     * Read-only system / runtime introspection (PHP, memory, composer, SAPI).
     */
    public function system(): SystemServiceInterface
    {
        return $this->app->make(SystemServiceInterface::class);
    }

    /**
     * Eloquent model helpers (resolved by its concrete class — no contract).
     */
    public function model(): ModelService
    {
        return $this->app->make(ModelService::class);
    }

    /**
     * HTTP client configuration builder (seeded from laranail.toolkit.http.*).
     */
    public function http(): HttpConfigurationServiceInterface
    {
        return $this->app->make(HttpConfigurationServiceInterface::class);
    }

    /**
     * Guard-aware authentication helpers (typed accessor over native auth()).
     */
    public function auth(): AuthenticationContextServiceInterface
    {
        return $this->app->make(AuthenticationContextServiceInterface::class);
    }

    /**
     * Geographic / country / language dataset helpers (Atlas module).
     */
    public function atlas(): AtlasServiceInterface
    {
        return $this->app->make(AtlasServiceInterface::class);
    }

    /**
     * Livewire component helpers (key generation, registration support).
     */
    public function livewire(): LivewireServiceInterface
    {
        return $this->app->make(LivewireServiceInterface::class);
    }

    /**
     * Tag-aware cache repository (get/put/remember/forget, namespaced keys).
     */
    public function cache(): CacheRepositoryInterface
    {
        return $this->app->make(CacheRepositoryInterface::class);
    }

    /**
     * Structured application logger (channel/level helpers over the log stack).
     */
    public function log(): LoggerServiceInterface
    {
        return $this->app->make(LoggerServiceInterface::class);
    }

    /**
     * Runtime settings store (dynamic, persisted-at-runtime key/value JSON).
     */
    public function settings(): SettingsStoreInterface
    {
        return $this->app->make(SettingsStoreInterface::class);
    }

    /**
     * Named-profile rate limiter (attempts/decay over the cache store).
     */
    public function rateLimiter(): RateLimiterServiceInterface
    {
        return $this->app->make(RateLimiterServiceInterface::class);
    }

    /**
     * Task scheduler helpers (cron/interval registration support).
     */
    public function scheduler(): SchedulerServiceInterface
    {
        return $this->app->make(SchedulerServiceInterface::class);
    }

    /**
     * A fresh CSPRNG secure-token / OTP-code builder (signed or unsigned).
     */
    public function token(): Token
    {
        return Token::unsigned();
    }

    /**
     * A fresh random-password builder (defaults to the `strong` preset).
     */
    public function password(): Password
    {
        return Password::strong();
    }

    /**
     * A fresh EFF-diceware passphrase builder (defaults to the `memorable` preset).
     */
    public function passphrase(): Passphrase
    {
        return Passphrase::memorable();
    }
}
