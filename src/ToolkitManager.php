<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit;

use Illuminate\Contracts\Foundation\Application;
use Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService;
use Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface;

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
}
