# Installation

## Requirements

| | |
|---|---|
| PHP | `^8.4.1 \|\| ^8.5` |
| Laravel | `^13.0` |
| Extensions | `ext-fileinfo`, `ext-mbstring` (also `ext-zip` for the Zip archiver) |

The toolkit surfaces a live requirements check under `php artisan about`
(section **Laranail Toolkit**): PHP version vs. the `8.4.1` floor, the presence
of the `json` / `mbstring` / `fileinfo` extensions, and storage writability.

## Install

```bash
# laranail/toolkit and laranail/console are not on Packagist yet: point Composer at GitHub.
composer config repositories.laranail-console vcs https://github.com/laranail/console
composer config repositories.laranail-toolkit vcs https://github.com/laranail/toolkit
composer require laranail/toolkit:^0.2
```

`ToolkitServiceProvider` is auto-registered through Laravel package discovery.
Migrations, views and translations are loaded automatically; you only publish
assets you want to own or customize. Views and translations answer to the
canonical `laranail/toolkit::` namespace and to the `laranail-toolkit::` spelling,
over the same files:

```php
view('laranail/toolkit::blade-javascript');   // canonical
view('laranail-toolkit::blade-javascript');   // also resolves
```

Published files land under `laranail-toolkit` (below), which is where the
`laranail-toolkit::` namespace reads overrides from.

## Publish tags

Publish tags use the namespaced `laranail::toolkit-*` convention.

| Tag | Publishes to |
|-----|--------------|
| `laranail::toolkit-config` | all configs under the dotted namespace — `config/laranail/toolkit.php`, `…/toolkit/feature-toggles.php`, `…/toolkit/atlas.php`, `…/toolkit/security.php` (editing them overrides `config('laranail.toolkit.*')`; `security` holds the common-password / EFF-wordlist / redaction datasets read by `SecurityData`) |
| `laranail::toolkit-migrations` | `database/migrations/*` |
| `laranail::toolkit-views` | `resources/views/vendor/laranail-toolkit` |
| `laranail::toolkit-translations` | `lang/vendor/laranail-toolkit` |
| `laranail::toolkit-stubs` | `stubs/vendor/laranail-toolkit` (CRUD stubs) |

Example:

```bash
php artisan vendor:publish --tag=laranail::toolkit-config
php artisan vendor:publish --tag=laranail::toolkit-migrations
php artisan migrate
```

### Used directly from the package — no publishing

These are resolved from the package and need **no** `vendor:publish`:

- **Services** — `Services\{CacheService, SettingsStore, SchedulerService,
  RateLimiterService, LogService}` (container-bound by their contracts + concrete
  class; inject or `app(...)` them).
- **Support helpers** — `Support\{QueryParameters, CollectionFilter, Environment,
  AuthHelper}` (static utilities).
- **`reject_common_passwords`** validation rule (registered via the package).
- **`ApiResponseTrait`** (`use` it from the package namespace).
- **`AccessLog`** model, bound as `app('laranail.toolkit.access-log')` (a fresh
  instance per resolve); extend it in your app if needed.
- **`Helper`**, bound as the shared `app('laranail.toolkit.helper')`.

> The bare container keys `app('AccessLog')` and `app('helper')` are deprecated
> aliases. They still resolve the same objects, raise one `E_USER_DEPRECATED`
> notice per process naming the replacement, and will be removed no earlier than
> the next minor after 0.2. The keys are also available as
> `ToolkitServiceProvider::ACCESS_LOG` and `ToolkitServiceProvider::HELPER`.

[← Docs index](../README.md#documentation)
