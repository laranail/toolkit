# Atlas module

A clean façade over a real country/locale dataset
([`rinvex/countries`](https://github.com/rinvex/countries)) plus a slim
Laravel-locale registry. Bound through a deferred provider (alias
`laranail.atlas`, facade `Atlas`). Read-only and deterministic — the data
package ships static JSON, so the derived lists are cached.

```php
use Simtabi\Laranail\Toolkit\Modules\Atlas\Atlas;
use Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService;
```

## Countries

```php
$all = Atlas::countries();        // ['US' => ['name' => 'United States', ...], ...]
$us  = Atlas::country('US');      // by ISO2
$usa = Atlas::country('usa');     // by ISO3 (case-insensitive); null if unknown
```

Each country summary:

| Key | |
|-----|---|
| `name` | Common name. |
| `official_name` | Official name. |
| `native_name` | Endonym. |
| `iso2` / `iso3` | ISO 3166-1 alpha-2 / alpha-3 codes. |
| `currency` | ISO 4217 currency code. |
| `calling_code` | International dialling code. |
| `emoji` | Flag emoji. |
| `continent` | Continent code (`AF`, `AN`, `AS`, `EU`, `NA`, `OC`, `SA`). |
| `continent_name` | Continent English name. |
| `region` | Geographic region (e.g. `Americas`). |
| `subregion` | Geographic subregion (e.g. `Northern America`). |

The geographic block (`continent` / `continent_name` / `region` / `subregion`)
is derived from the data package's long-list `geo` block — never hand-mapped.

### Select boxes

`forSelectBox()` returns a `code => label` map for an HTML `<select>`, sorted by
label:

```php
Atlas::forSelectBox();                              // ['AF' => 'Afghanistan', ..., 'US' => 'United States']
Atlas::forSelectBox('official_name', iso3: true);   // ['USA' => 'United States of America', ...]
```

Allowed label keys are `name`, `official_name`, `native_name`; an unknown key
falls back to the configured `default_label` (or `name`).

## Continents & regions

Continent / region grouping is derived from the data package's `geo` block and
cached like the other lists.

```php
Atlas::continents();              // ['AF' => 'Africa', 'AN' => 'Antarctica', 'AS' => 'Asia', 'EU' => 'Europe', 'NA' => 'North America', 'OC' => 'Oceania', 'SA' => 'South America']
Atlas::countriesByContinent();    // ['AF' => [<country>, ...], 'AN' => [...], ..., 'SA' => [...]] — every continent keyed, even if empty
Atlas::countriesInContinent('EU');     // by code
Atlas::countriesInContinent('Europe'); // or English name, case-insensitive; [] if unknown
Atlas::continentForCountry('KE');      // 'AF' — accepts ISO2 or ISO3; null if unknown
Atlas::continentForCountry('FR');      // 'EU'
Atlas::regions();                 // ['Africa', 'Americas', 'Asia', 'Europe', 'Oceania'] — sorted
Atlas::subregions();              // ['Australia and New Zealand', 'Caribbean', ..., 'Western Europe'] — sorted
```

## Currencies & timezones

```php
Atlas::currencies();   // ['AED', 'AFN', ..., 'USD', ...] — sorted, unique ISO 4217 codes
Atlas::timezones();    // ['Africa/Abidjan', ..., 'UTC', ...] — all IANA identifiers, sorted
```

## Languages & locales

The data package is country-centric, so the Laravel-locale registry lives in the
single, publishable `config/atlas.php` config under the `languages` key — merged
under `laranail.toolkit.atlas.languages` (ported, de-bloated, from the legacy
`Atlas\Languages`):

```php
Atlas::languages();   // ['en_US' => ['iso639_1' => 'en', 'locale' => 'en_US', 'native_name' => 'English', 'dir' => 'ltr', 'flag' => 'us'], ...]
Atlas::locales();     // ['af', 'ar', ..., 'en_US', ...]
```

`availableLocales()` filters the registry down to the locales that actually have
a translation directory under `resource_path('lang')` (the `vendor` directory is
skipped; a bare ISO 639-1 directory such as `en` matches the first `en_*` entry):

```php
Atlas::availableLocales();   // ['en_US' => ['locale' => 'en_US', 'name' => 'English', 'flag' => 'us'], ...]
```

## Configuration

Everything lives in **one** config file — `config/atlas.php`, merged under
`laranail.toolkit.atlas`:

```php
// config/atlas.php (the package file) → merged into config('laranail.toolkit.atlas.*')
'default_label' => env('LARANAIL_ATLAS_DEFAULT_LABEL', 'name'),   // 'name' | 'official_name' | 'native_name'
'cache_ttl'     => env('LARANAIL_ATLAS_CACHE_TTL', 1440),         // minutes; 0 to recompute every call
'continents'    => ['AF' => 'Africa', ..., 'SA' => 'South America'],  // continent code => English name
'languages'     => ['en_US' => ['iso639_1' => 'en', ...], ...],       // Laravel-locale registry
```

The continent display-name map read by `continents()` and the Laravel-locale
registry read by `languages()` / `locales()` / `availableLocales()` are both
sections of this single file — override or extend them there.

Publish it with:

```bash
php artisan vendor:publish --tag=laranail::toolkit-config
```

[← Docs index](../../README.md#documentation)
