<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array<string, array{name: string, official_name: string, native_name: string, iso2: string, iso3: string, currency: string, calling_code: string, emoji: string}> countries()
 * @method static array{name: string, official_name: string, native_name: string, iso2: string, iso3: string, currency: string, calling_code: string, emoji: string}|null           country(string $code)
 * @method static array<string, string>                                                                                                                                             forSelectBox(string $label = 'name', bool $iso3 = false)
 * @method static array<int, string>                                                                                                                                                currencies()
 * @method static array<int, string>                                                                                                                                                timezones()
 * @method static array<string, array{iso639_1: string, locale: string, native_name: string, dir: string, flag: string}>                                                            languages()
 * @method static array<int, string>                                                                                                                                                locales()
 * @method static array<string, array{locale: string, name: string, flag: string}>                                                                                                  availableLocales()
 *
 * @see AtlasService
 */
class Atlas extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return AtlasServiceInterface::class;
    }
}
