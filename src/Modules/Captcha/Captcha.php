<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface                 getProvider(?string $name = null)
 * @method static \Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult                verify(string $token, array<string, mixed> $options = [], ?string $provider = null, ?string $remoteIp = null)
 * @method static array<string, \Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult> verifyWithAllProviders(string $token, array<string, mixed> $options = [], ?string $remoteIp = null)
 * @method static CaptchaService                                                                     registerProvider(\Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface $provider, ?string $name = null)
 * @method static string                                                                             getSiteKey(?string $provider = null)
 * @method static bool                                                                               hasProvider(string $name)
 * @method static CaptchaService                                                                     setDefaultProvider(string $name)
 * @method static string                                                                             getDefaultProvider()
 * @method static array<int, string>                                                                 getProviderNames()
 * @method static bool                                                                               hasConfiguredProvider()
 *
 * @see CaptchaService
 */
class Captcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CaptchaService::class;
    }
}
