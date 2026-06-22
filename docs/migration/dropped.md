# Dropped legacy code — removal record

This package was ported from the pre-1.0 `Simtabi\Laranail` monolith. The
porting matrix kept only the genuine **delta** over Laravel 13 / PHP 8.3–8.5
natives. Everything below was **dropped** rather than ported, because Laravel
or PHP now ships an equivalent (or better) primitive, or because the legacy
code carried `laranail/*` / `pheg` dependencies that are out of scope for this
package.

Each row cites the native replacement so consumers can migrate.

> For the **complete** per-symbol accounting (all 279 legacy public types, classified
> migrated / relocated / dropped), see **[MIGRATION.md](MIGRATION.md)**. This file is
> the cited drop-rationale subset.

| Item | Why dropped | Native / replacement | Source URL |
|------|-------------|----------------------|------------|
| `Support/Traits/Concerns/ConditionalRunner` + `RunsConditionally` | Re-implements conditional method chaining. | `Illuminate\Support\Traits\Conditionable` — `->when()` / `->unless()`. | https://laravel.com/api/13.x/Illuminate/Support/Traits/Conditionable.html |
| `Support/Utilities/SystemIO/DiskSpaceValidator` (~960 lines) | Hand-rolled disk-usage maths over native calls. | PHP `disk_free_space()` / `disk_total_space()`. | https://www.php.net/manual/en/function.disk-free-space.php |
| Foundation logger service / `LoggingUtil` duplication | Wraps the logger and request-scoped metadata. | Native `Log` facade + `Context` facade (Laravel 11+). Existing `Utilities/LoggingUtil.php` left as-is; no new logger added. | https://laravel.com/docs/13.x/context |
| `Support/Services/Atlas/Countries.php` + `Languages.php` | Ships large static country/language datasets that drift and bloat the package. | Recommend `rinvex/countries`. | https://github.com/rinvex/countries |
| Native Blade directive re-implementations: `@checked @selected @disabled @readonly @required @class @style @session @vite @js @fragment @error @once @pushOnce @prependOnce @prepend @aware @props`, plus `@mix` (superseded by `@vite`), `@dump`/`@dd`, `@haserror` (≈ `@error`), `@selectedif` (≈ `@selected`), `@count`/`@nl2br`/`@kebab`/`@snake`/`@camel` (trivial / compile-time-broken). | All have native Laravel 11–13 directives or are one-liner expression echoes. | https://laravel.com/docs/13.x/blade#blade-directives |
| `Foundation/Services/PackageService.php` god-class | Centralised config-merge / publish / migration loading. | Superseded by `ToolkitServiceProvider` (`mergeConfigFrom`, `publishes`, `loadMigrationsFrom`). | https://laravel.com/docs/13.x/packages |
| `Support/Utilities/Username.php` | **Not dropped — MIGRATED(merged) in G2-3.** The pheg-bound name→username suggestion was folded into `Traits\HasFormatters::suggestUsername()` + `Helpers\XHelper::nameToUsernames()`, with the `pheg()->name()->name2username()` call **inlined to native `Str`** (`Str::slug`/`Str::substr`). See MIGRATION.md. | Native `Str` + `HasFormatters::suggestUsername()` / `formattedUsername()`. | https://laravel.com/docs/13.x/strings |
| `Support/Utilities/Auth.php` | Thin wrappers over the auth guard, carrying pheg deps. | Native `Auth` facade / `auth()` helper. | https://laravel.com/docs/13.x/authentication |
| `Support/Utilities/DatabaseSession.php` | Duplicates the database session driver. | Native database session driver (`SESSION_DRIVER=database`). | https://laravel.com/docs/13.x/session#database |
| `Laravel/Http/Middleware/EmailObfuscatorMiddleware` | Obfuscated emails in rendered HTML responses via `pheg()->email()->obfuscate()`. The `pheg` dependency is out of scope and HTML email-cloaking is non-core. **DROPPED** rather than ported — the rest of the legacy HTTP layer (`ApiMiddleware`, `ApiRequestMiddleware`, `ApiResponseMiddleware`, `BaseRequest`, `ShovelHttpInterface`) **was** migrated in G1. | Implement as a small response middleware over a maintained obfuscation library, or cloak client-side. | (no native replacement) |
| `Laravel/Http/Controllers/BaseController` + `Laravel/Http/Requests/ApiRequest` | Thin scaffolding. `BaseController` is superseded by `Http\Controllers\CrudController`; `ApiRequest` only overrode `failedValidation()` to emit a JSON error envelope. | `Http\Controllers\CrudController`; build the error envelope with `Traits\ApiResponseTrait`, or override `failedValidation()` on a `Http\Requests\BaseRequest` subclass. | (in-repo) `src/Http/` |
| `Laravel/Jobs/BaseJob` | Trivial scaffolding; nothing dispatched it. | Native `ShouldQueue` + `Illuminate\Foundation\Queue\Queueable`. | https://laravel.com/docs/13.x/queues |
| `Laravel/Listeners/BaseListener` + `LicenseListener` | Empty/licence-check scaffolding with no concrete dispatcher. | Native event listeners + auto-discovery. | https://laravel.com/docs/13.x/events |
| `Laravel/Observers/BaseObserver` | Empty base class. | Native model observers + `#[ObservedBy]`. | https://laravel.com/docs/13.x/eloquent#observers |
| `Shared/Events/*` (e.g. `CacheEvents`) | Trivial event POPOs nothing dispatched. | Define events natively where actually needed. | https://laravel.com/docs/13.x/events |
| `Support/Resources/AvatarResource.php` / `GravatarResource.php` | Old result wrappers. | Superseded by the new module DTOs `Modules\Avatar\AvatarResolution` and `Modules\Gravatar\GravatarResolution`. | (in-repo) `src/Modules/Avatar/` |
| `Foundation/Services/ModelFormatterService` + its contract | Mostly stubs (address/display-name returned `''`); delegated to pheg. | Folded the working formatters straight into the ported `Traits\HasFormatters` (native `Carbon` / `Str`). | https://laravel.com/docs/13.x/strings |
| Stub commands: `AssetCommand`, `CronJobCommand`, `DatabaseCommand`, `InitializeApplication`, `LicenseCommand`, `MaintenanceCommand`, `TidyCommand`, `SetAppNamespace` | Stubs or duplicates of native tooling (`TidyCommand` ≈ Pint, `MaintenanceCommand` ≈ `php artisan down`). `MacrosCommand` not referenced by the ported macro system. | `php artisan down` / Laravel Pint / native scheduler. | https://laravel.com/docs/13.x/configuration#maintenance-mode |
| Small string/array helper duplicates | Re-implement primitives now native. | PHP 8.4 `array_find` / `array_any` / `array_all`, `mb_*`; PHP 8.3 `json_validate()`. | https://www.php.net/releases/8.4/en.php |
| `Laravel/Providers/BladeServiceProvider` (legacy) | Registered every directive incl. native duplicates. | Replaced by the trimmed `src/Providers/BladeServiceProvider.php` (custom-only). | (in-repo) `src/Providers/BladeServiceProvider.php` |
| `Support/Utilities/SystemIO/RequirementsChecker` (apache-module / generic-config probes) | Apache-module + nested-config probes are environment-specific and rarely useful in a CLI/queue context. | Kept only PHP-version, extension, and directory-writability checks in the new thin `Support\Diagnostics\RequirementsDiagnostics`, surfaced via `php artisan about`. | https://laravel.com/docs/13.x/artisan#about-command-customization |

## Kept (the genuine delta)

For completeness, the items that **were** ported (because they are dep-free,
unique, and have no native equivalent):

- `Support\Scopes\ArchiveScope` + `Traits\HasArchiver` — soft-archive on an
  `archived_at` column (coexists with native soft deletes on `deleted_at`).
- `Traits\HasAvatar` — rewired to the `Avatar` / `Gravatar` module contracts.
- `Traits\HasFormatters` — the working subset, on native `Carbon` / `Str`.
- `Support\FilePathGuard` (used by `Traits\FileProcessingTrait`) — `..` /
  null-byte path-traversal guard.
- `Support\Diagnostics\RequirementsDiagnostics` — thin PHP/extension/writable
  checks surfaced under `php artisan about`.
- `Laravel\Blade\BladeServiceProvider` — only the directives with no native
  counterpart (`@istrue`/`@isfalse`/`@isnull`/`@isnotnull`, `@routeis`/
  `@routeisnot`/`@activeifroute`, `@instanceof`/`@typeof`, `@repeat`, the icon
  shorthands, `@window`, `@base64image`).
