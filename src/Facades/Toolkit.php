<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Toolkit\ToolkitManager;

/**
 * Unified entry facade for the toolkit's feature modules.
 *
 * @method static \Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface     avatar()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface gravatar()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService            captcha()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface archiver()
 *
 * @see ToolkitManager
 */
class Toolkit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ToolkitManager::class;
    }
}
