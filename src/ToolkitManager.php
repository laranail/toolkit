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
use Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationHelperServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
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
     * Database introspection + maintenance helpers (confined to the base path).
     */
    public function db(): DatabaseServiceInterface
    {
        return $this->app->make(DatabaseServiceInterface::class);
    }

    /**
     * Alias of {@see self::db()} for callers preferring the long name.
     */
    public function database(): DatabaseServiceInterface
    {
        return $this->db();
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
    public function auth(): AuthenticationHelperServiceInterface
    {
        return $this->app->make(AuthenticationHelperServiceInterface::class);
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
}
