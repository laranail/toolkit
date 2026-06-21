<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Toolkit\Modules\Avatar\Contracts\AvatarServiceInterface;

/**
 * @method static AvatarServiceInterface                                                        setName(?string $name)
 * @method static AvatarServiceInterface                                                        setWidth(int $width)
 * @method static AvatarServiceInterface                                                        setHeight(int $height)
 * @method static AvatarServiceInterface                                                        setSize(int $width, int $height)
 * @method static AvatarServiceInterface                                                        setShape(string $shape)
 * @method static AvatarServiceInterface                                                        setBackgroundColor(string $color)
 * @method static AvatarServiceInterface                                                        setForegroundColor(string $color)
 * @method static AvatarServiceInterface                                                        setColors(string $backgroundColor, string $foregroundColor)
 * @method static AvatarServiceInterface                                                        setChars(int $chars)
 * @method static AvatarServiceInterface                                                        setFontSize(int $size)
 * @method static AvatarServiceInterface                                                        setBorderSize(int $size)
 * @method static AvatarServiceInterface                                                        setBorderColor(string $color)
 * @method static AvatarServiceInterface                                                        setUppercase(bool $uppercase)
 * @method static AvatarServiceInterface                                                        setAscii(bool $ascii)
 * @method static AvatarServiceInterface                                                        setCacheEnabled(bool $enabled)
 * @method static AvatarServiceInterface                                                        setCacheTtl(int $ttl)
 * @method static AvatarServiceInterface                                                        setFontPath(string $path)
 * @method static AvatarServiceInterface                                                        setQuality(int $quality)
 * @method static string                                                                        generate()
 * @method static string                                                                        generateBase64()
 * @method static string                                                                        generateDataUri()
 * @method static bool                                                                          save(string $path)
 * @method static string                                                                        makeInitials()
 * @method static ?string                                                                       getGravatar(int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid')
 * @method static string                                                                        getGravatarForEmail(string $email, int $size = 200, bool $isHttps = false, string $rating = 'g', string $default = 'monsterid')
 * @method static \Simtabi\Laranail\Toolkit\Modules\Avatar\DataTransferObjects\AvatarResolution getAvatar(string|\Illuminate\Database\Eloquent\Model|callable $source, array<string, mixed> $options = [])
 * @method static string                                                                        getAvatarUrl(string|\Illuminate\Database\Eloquent\Model|callable $source, array<string, mixed> $options = [])
 *
 * @see AvatarServiceInterface
 */
class Avatar extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AvatarServiceInterface::class;
    }
}
