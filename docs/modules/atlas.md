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

### Select boxes

`forSelectBox()` returns a `code => label` map for an HTML `<select>`, sorted by
label:

```php
Atlas::forSelectBox();                              // ['AF' => 'Afghanistan', ..., 'US' => 'United States']
Atlas::forSelectBox('official_name', iso3: true);   // ['USA' => 'United States of America', ...]
```

Allowed label keys are `name`, `official_name`, `native_name`; an unknown key
falls back to the configured `default_label` (or `name`).

## Currencies & timezones

```php
Atlas::currencies();   // ['AED', 'AFN', ..., 'USD', ...] — sorted, unique ISO 4217 codes
Atlas::timezones();    // ['Africa/Abidjan', ..., 'UTC', ...] — all IANA identifiers, sorted
```

## Languages & locales

The data package is country-centric, so the Laravel-locale registry lives in the
publishable `config/languages.php` config — merged under
`laranail.toolkit.languages` (ported, de-bloated, from the legacy
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

```php
// config/laranail-toolkit-atlas.php → laranail.toolkit.atlas
'default_label' => env('LARANAIL_ATLAS_DEFAULT_LABEL', 'name'),   // 'name' | 'official_name' | 'native_name'
'cache_ttl'     => env('LARANAIL_ATLAS_CACHE_TTL', 1440),         // minutes; 0 to recompute every call
```

The Laravel-locale registry read by `languages()` / `locales()` /
`availableLocales()` ships as a second, publishable config file merged under
`laranail.toolkit.languages` (`config/laranail-toolkit-languages.php` once
published) — override or extend it there.

Publish both with the same tag:

```bash
php artisan vendor:publish --tag=laranail-toolkit-atlas
```

[← Docs index](../../README.md#documentation)
