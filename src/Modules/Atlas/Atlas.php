<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Atlas;

use Illuminate\Support\Facades\Facade;

/**
 * @phpstan-import-type CountrySummary from AtlasServiceInterface
 * @phpstan-import-type LanguageEntry from AtlasServiceInterface
 *
 * @method static array<string, CountrySummary>                                    countries()
 * @method static CountrySummary|null                                              country(string $code)
 * @method static array<string, string>                                            forSelectBox(string $label = 'name', bool $iso3 = false)
 * @method static array<int, string>                                               currencies()
 * @method static array<int, string>                                               timezones()
 * @method static array<string, string>                                            continents()
 * @method static array<string, list<CountrySummary>>                              countriesByContinent()
 * @method static list<CountrySummary>                                             countriesInContinent(string $continent)
 * @method static string|null                                                      continentForCountry(string $code)
 * @method static array<int, string>                                               regions()
 * @method static array<int, string>                                               subregions()
 * @method static array<string, LanguageEntry>                                     languages()
 * @method static array<int, string>                                               locales()
 * @method static array<string, array{locale: string, name: string, flag: string}> availableLocales()
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
