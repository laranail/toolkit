# Installation

## Requirements

| | |
|---|---|
| PHP | `^8.3 \|\| ^8.4 \|\| ^8.5` |
| Laravel | `^13.0` |
| Extensions | `ext-fileinfo`, `ext-mbstring` (also `ext-zip` for the Zip archiver) |

The toolkit surfaces a live requirements check under `php artisan about`
(section **Laranail Toolkit**): PHP version vs. the `8.3.0` floor, the presence
of the `json` / `mbstring` / `fileinfo` extensions, and storage writability.

## Install

```bash
composer require laranail/toolkit
```

`ToolkitServiceProvider` is auto-registered through Laravel package discovery.
Migrations, views (namespace `laranail-toolkit`), and translations are loaded
automatically; you only publish assets you want to own or customize.

## Publish tags

Each asset has its own `vendor:publish` tag so you can pull in exactly what you
need.

| Tag | Publishes to |
|-----|--------------|
| `laranail-toolkit-config` | `config/laranail-toolkit.php` |
| `laranail-toolkit-feature-toggles` | `config/feature-toggles.php` |
| `laranail-toolkit-migrations` | `database/migrations/*` |
| `laranail-toolkit-views` | `resources/views/vendor/laranail-toolkit` |
| `laranail-toolkit-lang` | `lang/vendor/laranail-toolkit` |
| `laranail-toolkit-stubs` | `stubs/vendor/laranail-toolkit` (CRUD stubs) |
| `laranail-toolkit-models` | `app/Models` (e.g. `AccessLog`) |
| `laranail-toolkit-api-response-trait` | `app/Traits/ApiResponseTrait.php` |
| `laranail-toolkit-validation-rules` | `app/Rules/RejectCommonPasswords.php` |

Utility classes can each be copied into `app/Utilities/`:

| Tag | Utility |
|-----|---------|
| `laranail-toolkit-caching` | `CachingUtil` |
| `laranail-toolkit-config-util` | `ConfigUtil` |
| `laranail-toolkit-scheduler` | `SchedulerUtil` |
| `laranail-toolkit-query-parameter` | `QueryParameterUtil` |
| `laranail-toolkit-rate-limiter` | `RateLimiterUtil` |
| `laranail-toolkit-paginator` | `PaginationUtil` |
| `laranail-toolkit-filtering` | `FilteringUtil` |
| `laranail-toolkit-logging` | `LoggingUtil` |

Example:

```bash
php artisan vendor:publish --tag=laranail-toolkit-config
php artisan vendor:publish --tag=laranail-toolkit-migrations
php artisan migrate
```

You do not need to publish a utility to use it — every utility, trait, and
module service is already bound in the container and usable directly.

[← Docs index](../README.md#documentation)
