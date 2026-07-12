<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Gravatar;

use Illuminate\Support\Facades\Facade;

/**
 * @method static GravatarServiceInterface                                      setEmail(string $email)
 * @method static GravatarServiceInterface                                      setSize(int $size)
 * @method static GravatarServiceInterface                                      setHttps(bool $https)
 * @method static GravatarServiceInterface                                      setRating(string $rating)
 * @method static GravatarServiceInterface                                      setDefaultImage(string $defaultImage)
 * @method static GravatarServiceInterface                                      setForceDefault(bool $forceDefault)
 * @method static GravatarServiceInterface                                      setCustomDefaultUrl(?string $customUrl)
 * @method static string                                                        generate()
 * @method static \Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarResolution resolve()
 * @method static string                                                        hashEmail(string $email)
 * @method static bool                                                          isValidEmail(string $email)
 *
 * @see GravatarServiceInterface
 */
class Gravatar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GravatarServiceInterface::class;
    }
}
