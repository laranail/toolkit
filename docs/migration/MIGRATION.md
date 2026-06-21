# Migration ledger — legacy `laranail/laranail` → the new family

This is the authoritative accounting of **every** public symbol in the legacy
monolith (`old/`, namespace `Simtabi\Laranail\`) and where it went. It is generated
by diffing a frozen reflection snapshot of the old public API
(`tests/Fixtures/Legacy/old-api-surface.json`, **279 public types**) against the
current package surfaces, and curated below. The cited drop rationale lives in
[`dropped.md`](dropped.md); this file is the complete per-symbol view.

## Summary

| Status | Count | Meaning |
|---|---:|---|
| **MIGRATED** | 37 | Carried into `laranail/toolkit` (often renamed — `…DTO`/`…Facade`/`…Resource` suffixes dropped in the flat layout). |
| **RELOCATED** | 17 | Moved to a sibling package: **16** notification classes → `laranail/notifications`, the `NotificationChannel` enum → same. |
| **DROPPED** | 225 | Not carried over — native-duplicative, consolidated, or out-of-scope. See the buckets below. |
| **Total** | 279 | |

> Separately, the `Command` base + `SupportsNamespacedNames` trait (a
> `toolkit` v0.1.0 addition, **not** an `old/` symbol) were **RELOCATED to
> `laranail/console`** — the toolkit now depends on it (see CHANGELOG / R0).

## RELOCATED → `laranail/notifications`

All 16 legacy `Features\Notifications\**` classes + `Shared\Enums\NotificationChannelEnum`
were rebuilt (hardened) in `laranail/notifications` (`Simtabi\Laranail\Notifications\*`):
the service, channel contract, 13 channels, and the result object — now with a typed
`NotificationMessage` DTO, an enum channel **allow-list**, SSRF-guarded outbound
channels, and a serializable queue job. `composer require laranail/notifications`.

## DROPPED — by bucket (with rationale)

| Bucket | ~count | Why |
|---|---:|---|
| `Laravel\Macros\*` | 104 | The 107-file micro-macro library **consolidated** into the grouped `Macros\{Str,Arr,Collection,QueryBuilder,Blueprint,Request}Macros` providers (kept subset); low-value ones (national-holiday / locale-date macros) dropped. Coverage asserted by the macro-inventory test. |
| `Foundation\Services\*` + `Foundation\Contracts\*` | ~41 | The service-locator service layer (`CacheService`, `FileService`, `ValidationService`, `RouteService`, `SessionService`, `DatabaseService`, `SystemService`, helper services, …) — **native-duplicative**. Superseded by native Laravel + the kept `Utilities\*` / `Traits\*`. These were the services the old `Laranail` facade fronted (see below). |
| `Laravel\Providers\*` | 10 | Per-macro sub-providers + `MacrosServiceProvider` → consolidated into `Macros\MacroServiceProvider`; the middleware provider dropped (register middleware in the app). |
| `Laravel\Http\*` | 7 | `BaseController`/`BaseRequest`/`ApiRequest` scaffolding → replaced by `Http\Controllers\CrudController`; `ApiResponse*Middleware` → `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency). |
| `Shared\Exceptions\*` + `Foundation\Exceptions\*` | 10 | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, `ModelNotFoundException`). |
| `Support\Traits\*` | 5 | `HasAuth`/`HasLivewire`/`HasGuzzleConfig`/`HasPackageTools`/`HasErrorStorage` — native Laravel or out of the toolkit's scope (livewire/package-tools/pheg). |
| `Support\Contracts\*` | 6 | Interfaces for the dropped service-locator services. |
| `Shared\Events\*` | 4 | Trivial event POPOs nothing dispatched. |
| `Foundation\Providers\*` | 3 | Superseded by `Providers\ToolkitServiceProvider` + native auto-discovery. |
| `Support\Resources\*` | 3 | Superseded by the module DTOs (`AvatarResolution`, `GravatarResolution`). |
| `Laravel\Services\*` | 3 | Native/duplicative (`ResponseBuilderService` ≈ `ApiResponseTrait`). |
| Misc (`Support\Utilities`, jobs/listeners/observers, stub commands) | — | Native replacements; cited in `dropped.md`. |

## The `Laranail` facade (the headline loss)

The legacy `Foundation\Laranail` service + `Foundation\Facades\LaranailFacade`
(registered as the **`Laranail`** alias) exposed **48 delegated methods** over the
now-dropped Foundation services. It was a service-locator anti-pattern. Crucially,
**almost every method is a native Laravel one-liner or covered by a kept utility/trait**
— so the *capability* is not lost, only the single grab-bag accessor:

| Legacy `Laranail::` methods | Replacement |
|---|---|
| `cache`, `clearCache` | `Utilities\CachingUtil` / native `Cache` facade |
| `checkIfFileExistsInStorage`, `getFileAsObject`, `pathToUploadedFileInstance`, `clearLogFiles`, `deleteStorageSymlink` | native `Storage` + `Traits\FileProcessingTrait` |
| `getAppUrl`, `getCurrentRouteInfo`, `isCurrentRoute`, `getActiveCssClassForRoute` | native `url()` / `route()` / `Route::currentRouteName()` / `request()->routeIs()` |
| `getErrorBagMessage`, `getCheckboxStatus` | native `$errors` bag / request helpers |
| `isValidDatabaseConnection`, `setDatabaseCredentials`, `registerModelObserver` | native `DB` / config + `#[ObservedBy]` |
| `existsInFilterKey`, `joinInFilterKey`, `removeFromFilterKey`, `saveJavaScriptCookies` | native `session()` / `Cookie` |
| `getComposerArray`, `getSystemEnv`, `getServerEnv`, `isSslIsInstalled`, `environment` | `Composer\InstalledVersions` / native `env()` / `request()->secure()` / `app()->environment()` |
| `arrayToDotNotation`, `mapKeyValuePairArray`, `sortItemWithChildren`, `sortSearchResults` | `Arr::dot` + the kept `Macros\ArrMacros` / `CollectionMacros` |
| `random`, `ucWords`, `generateUsername`, `generateEmailFromUsername`, `username` | native `Str` + `Traits\HasFormatters` |
| `faker`, `html`, `writeToConsoleOutput`, `logError`, `eloquent2selectbox`, `getModelItem`, `getClassNameFromClass` | native `fake()` / `Log` / `class_basename()` / Eloquent |
| `authHelper`, `isUserExists` | native `Auth` facade |
| `package`, `httpConfig`, `formatter` | `package` → **`laranail/package-tools`**; `httpConfig`/`formatter` → native config / `Traits\HasFormatters` |
| `generateLivewireComponentKey`, `livewire` | **dropped** (livewire-specific, out of scope) |

## Decisions for owner review (feeds R-Facade)

Nothing of substance is lost — but two **ergonomic** items are worth restoring, which
R-Facade will do unless you object:

1. **Module facade aliases** — register `Avatar`, `Gravatar`, `Captcha`, `Archiver`
   as Laravel aliases so `Avatar::…` works out of the box (currently they exist but
   aren't alias-registered).
2. **A small typed fluent `Toolkit` facade** — `Toolkit::avatar()/gravatar()/captcha()/archiver()`
   (+ a `Toolkit` alias) as the intended unified entry that replaces the old
   `Laranail` service-locator — typed, not a 48-method grab-bag.

**Question for you:** beyond (1) and (2), are there any specific dropped `Laranail::`
methods, traits, or exceptions above you want resurrected verbatim? Default: **no** —
they're native one-liners or out-of-scope. Flag any you want kept and R-Facade will
wire them.

---

## Appendix — full per-symbol table (all 279 legacy types)

<!-- generated; do not hand-edit -->
| Legacy symbol | Status | New location / reason |
|---|---|---|
| `Features\Archiver\Contracts\ArchiverServiceInterface` | MIGRATED | Toolkit\Modules\Archiver\ArchiverServiceInterface |
| `Features\Archiver\Facades\ArchiverFacade` | MIGRATED | Toolkit\Modules\Archiver\Archiver |
| `Features\Archiver\Services\ArchiveManager` | MIGRATED | Toolkit\Modules\Archiver\ArchiveManager |
| `Features\Archiver\Services\ArchiverService` | MIGRATED | Toolkit\Modules\Archiver\ArchiverService |
| `Features\Archiver\Services\Extractor` | MIGRATED | Toolkit\Modules\Archiver\Extractor |
| `Features\Archiver\Services\Tar` | MIGRATED | Toolkit\Modules\Archiver\Tar |
| `Features\Archiver\Services\TarGz` | MIGRATED | Toolkit\Modules\Archiver\TarGz |
| `Features\Archiver\Services\Zip` | MIGRATED | Toolkit\Modules\Archiver\Zip |
| `Features\Avatar\Contracts\AvatarServiceInterface` | MIGRATED | Toolkit\Modules\Avatar\AvatarServiceInterface |
| `Features\Avatar\DTOs\AvatarGenerationDTO` | MIGRATED | Toolkit\Modules\Avatar\AvatarGeneration |
| `Features\Avatar\DTOs\AvatarResolutionContextDTO` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolutionContext |
| `Features\Avatar\DTOs\AvatarResolutionDTO` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolution |
| `Features\Avatar\Facades\AvatarFacade` | MIGRATED | Toolkit\Modules\Avatar\Avatar |
| `Features\Avatar\Services\AvatarResolutionContext` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolutionContext |
| `Features\Avatar\Services\AvatarResolver` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolver |
| `Features\Avatar\Services\AvatarService` | MIGRATED | Toolkit\Modules\Avatar\AvatarService |
| `Features\Captcha\Contracts\CaptchaProviderInterface` | MIGRATED | Toolkit\Modules\Captcha\CaptchaProviderInterface |
| `Features\Captcha\Providers\HcaptchaProvider` | MIGRATED | Toolkit\Modules\Captcha\Providers\HcaptchaProvider |
| `Features\Captcha\Providers\RecaptchaProvider` | MIGRATED | Toolkit\Modules\Captcha\Providers\RecaptchaProvider |
| `Features\Captcha\Providers\TurnstileProvider` | MIGRATED | Toolkit\Modules\Captcha\Providers\TurnstileProvider |
| `Features\Captcha\Results\CaptchaVerificationResult` | MIGRATED | Toolkit\Modules\Captcha\CaptchaVerificationResult |
| `Features\Captcha\Services\CaptchaService` | MIGRATED | Toolkit\Modules\Captcha\CaptchaService |
| `Features\Gravatar\Contracts\GravatarServiceInterface` | MIGRATED | Toolkit\Modules\Gravatar\GravatarServiceInterface |
| `Features\Gravatar\DTOs\GravatarResolutionDTO` | MIGRATED | Toolkit\Modules\Gravatar\GravatarResolution |
| `Features\Gravatar\Facades\GravatarFacade` | MIGRATED | Toolkit\Modules\Gravatar\Gravatar |
| `Features\Gravatar\Services\GravatarService` | MIGRATED | Toolkit\Modules\Gravatar\GravatarService |
| `Features\Notifications\Channels\AbstractNotificationChannel` | RELOCATED | Notifications\Channels\AbstractNotificationChannel (laranail/notifications) |
| `Features\Notifications\Channels\AppleBusinessMessagesChannel` | RELOCATED | Notifications\Channels\AppleBusinessMessagesChannel (laranail/notifications) |
| `Features\Notifications\Channels\CacheChannel` | RELOCATED | Notifications\Channels\CacheChannel (laranail/notifications) |
| `Features\Notifications\Channels\ConsoleChannel` | RELOCATED | Notifications\Channels\ConsoleChannel (laranail/notifications) |
| `Features\Notifications\Channels\DatabaseChannel` | RELOCATED | Notifications\Channels\DatabaseChannel (laranail/notifications) |
| `Features\Notifications\Channels\DiscordChannel` | RELOCATED | Notifications\Channels\DiscordChannel (laranail/notifications) |
| `Features\Notifications\Channels\EmailChannel` | RELOCATED | Notifications\Channels\EmailChannel (laranail/notifications) |
| `Features\Notifications\Channels\FileChannel` | RELOCATED | Notifications\Channels\FileChannel (laranail/notifications) |
| `Features\Notifications\Channels\LogChannel` | RELOCATED | Notifications\Channels\LogChannel (laranail/notifications) |
| `Features\Notifications\Channels\PushChannel` | RELOCATED | Notifications\Channels\PushChannel (laranail/notifications) |
| `Features\Notifications\Channels\SlackChannel` | RELOCATED | Notifications\Channels\SlackChannel (laranail/notifications) |
| `Features\Notifications\Channels\SmsChannel` | RELOCATED | Notifications\Channels\SmsChannel (laranail/notifications) |
| `Features\Notifications\Channels\WebhookChannel` | RELOCATED | Notifications\Channels\WebhookChannel (laranail/notifications) |
| `Features\Notifications\Contracts\NotificationChannelInterface` | RELOCATED | Notifications\Contracts\NotificationChannelInterface (laranail/notifications) |
| `Features\Notifications\Services\NotificationService` | RELOCATED | Notifications\Services\NotificationService (laranail/notifications) |
| `Features\Notifications\Support\NotificationResult` | RELOCATED | Notifications\Support\NotificationResult (laranail/notifications) |
| `Foundation\Contracts\AuthenticationServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\CacheServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\DatabaseServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\FileServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\RouteServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\AuthenticationHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\ClassHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\CollectionHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\DatabaseFileServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\ErrorStorageServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\FakerHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\FileHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\HttpConfigurationServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\LivewireComponentServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\ModelFormatterServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\PackageServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\StringHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\SessionServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\SystemServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\UtilityServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\ValidationServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Exceptions\FileTooLargeException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Foundation\Exceptions\InvalidPathException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Foundation\Exceptions\LaranailException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Foundation\Exceptions\LaranailExceptionHandler` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Foundation\Facades\LaranailFacade` | DROPPED | The 48-method service-locator facade — see **The `Laranail` facade** section. Replaced by per-module facade aliases + the new fluent `Toolkit` facade (R-Facade). |
| `Foundation\Laranail` | DROPPED | The 48-method service-locator facade — see **The `Laranail` facade** section. Replaced by per-module facade aliases + the new fluent `Toolkit` facade (R-Facade). |
| `Foundation\Providers\LaranailEventServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Providers\LaranailHookServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Providers\LaranailServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Services\AuthenticationHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\AuthenticationService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\CacheService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ClassHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\CollectionHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\DatabaseFileService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\DatabaseService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ErrorStorageService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\FakerHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\FileHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\FileService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\HttpConfigurationService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\LivewireComponentService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ModelFormatterService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ModelService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\PackageService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\RouteService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\SessionService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\StringHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\SystemService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\UtilityService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ValidationService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Laravel\Commands\AssetCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\CronJobCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\DatabaseCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\InitializeApplication` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\LicenseCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\MacrosCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\MaintenanceCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\SetAppNamespace` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\TidyCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Http\Controllers\BaseController` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Middleware\ApiMiddleware` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Middleware\ApiRequestMiddleware` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Middleware\ApiResponseMiddleware` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Middleware\EmailObfuscatorMiddleware` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Requests\ApiRequest` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Http\Requests\BaseRequest` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`; `EmailObfuscatorMiddleware` dropped (pheg dependency); base controller/request scaffolding dropped. |
| `Laravel\Jobs\BaseJob` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Listeners\BaseListener` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Listeners\LicenseListener` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Macros\After` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\At` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Before` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Bind` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\BrazilianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CanadianDates` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CapitalizeWords` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CatchableProxy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ChunkBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CollectBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Decrement` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\DistanceBetween` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\DutchHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\EachCons` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Eighth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Error` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Extract` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FactoryBuilderMixin` | MIGRATED | Toolkit\Macros\FactoryBuilderMixin |
| `Laravel\Macros\Fifth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FilterMap` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstDifferentLengthAwarePaginator` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstOrFail` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstOrPush` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ForSelectBox` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Fourth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FrenchHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromJson` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromPairs` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GenerateName` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GermanHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GetFile` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GetNth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Glob` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GroupByModel` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Head` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\HighlightWords` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Human` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IfAny` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IfEmpty` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IfMacro` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Increment` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IndianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IndonesianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Initials` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfter` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfterKey` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAt` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertBefore` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertBeforeKey` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Interpolate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IsEquals` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ItalianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\KenyanHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Krsort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Ksort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\LinesCount` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\MacroSupport` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Matches` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Message` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\MultiNationalDates` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Ninth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\None` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Paginate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PaginateFirstDifferent` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PaginateWithPrevious` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Paginator` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ParallelMap` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Path` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Pdf` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PluckMany` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PluckToArray` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Prioritize` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ReadingMinutes` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Recursive` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\RenameKeys` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ReplaceInKeys` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ResponseMacros` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Rotate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Round5` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Rsort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Second` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SectionBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Seventh` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SimplePaginate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Sixth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SliceBefore` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\StripTags` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Success` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SwedishHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Tail` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Tenth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Third` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToPairs` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Transpose` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\TryCatch` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\UkrainianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\UsDates` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Validate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhenEquals` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereContains` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereEndsWith` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereStartsWith` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WithSize` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WordsCount` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ZambianHolidays` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Observers\BaseObserver` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Providers\ArchiverServiceProvider` | MIGRATED | Toolkit\Modules\Archiver\ArchiverServiceProvider |
| `Laravel\Providers\ArrMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\BladeServiceProvider` | MIGRATED | Toolkit\Providers\BladeServiceProvider |
| `Laravel\Providers\BlueprintMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\CarbonMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\CollectionMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\LaravelMiddlewareServiceProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\MacrosServiceProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\QueryBuilderMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\RequestMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\ResponseMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Providers\StringMacroProvider` | DROPPED | Macro sub-providers consolidated into the single `Macros\MacroServiceProvider`; middleware provider dropped (register middleware in the app). |
| `Laravel\Services\ImportDatabaseService` | DROPPED | Native/duplicative (`ResponseBuilderService` ≈ `ApiResponseTrait`; DB import via native tooling). |
| `Laravel\Services\ResponseBuilderService` | DROPPED | Native/duplicative (`ResponseBuilderService` ≈ `ApiResponseTrait`; DB import via native tooling). |
| `Laravel\Services\SystemService` | DROPPED | Native/duplicative (`ResponseBuilderService` ≈ `ApiResponseTrait`; DB import via native tooling). |
| `Shared\Enums\AvatarFont` | MIGRATED | Toolkit\Modules\Avatar\AvatarFont |
| `Shared\Enums\NotificationChannelEnum` | RELOCATED | → `laranail/notifications` (the `NotificationChannel` enum). |
| `Shared\Events\BaseEvent` | DROPPED | Trivial event POPOs nothing dispatched; define events natively where needed (cited in `dropped.md`). |
| `Shared\Events\CacheEvents` | DROPPED | Trivial event POPOs nothing dispatched; define events natively where needed (cited in `dropped.md`). |
| `Shared\Events\EnvironmentEvents` | DROPPED | Trivial event POPOs nothing dispatched; define events natively where needed (cited in `dropped.md`). |
| `Shared\Events\LicenseEvents` | DROPPED | Trivial event POPOs nothing dispatched; define events natively where needed (cited in `dropped.md`). |
| `Shared\Events\RequirementsEvents` | DROPPED | Trivial event POPOs nothing dispatched; define events natively where needed (cited in `dropped.md`). |
| `Shared\Exceptions\AuthenticationException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Shared\Exceptions\CollectionItemNotFound` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Shared\Exceptions\ImmutableDataException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Shared\Exceptions\MissingUuidColumnException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Shared\Exceptions\ModelException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Shared\Exceptions\UuidException` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Support\Contracts\CacheHelperInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\LoggerServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ResponseBuilderServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ResponseMacroInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ShovelHttpInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\SystemServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Eloquent\Scopes\ArchiveScope` | MIGRATED | Toolkit\Support\Scopes\ArchiveScope |
| `Support\Facades\LanguagesFacade` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Resources\AvatarGenerationResource` | MIGRATED | Toolkit\Modules\Avatar\AvatarGeneration |
| `Support\Resources\AvatarResolutionResource` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolution |
| `Support\Resources\GravatarResolutionResource` | MIGRATED | Toolkit\Modules\Gravatar\GravatarResolution |
| `Support\Services\Atlas\Countries` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Services\Atlas\Languages` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Traits\HasArchiver` | MIGRATED | Toolkit\Traits\HasArchiver |
| `Support\Traits\HasAuth` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\HasErrorStorage` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\HasGuzzleConfig` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\HasPackageTools` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\Livewire\HasLivewire` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\Models\HasAvatar` | MIGRATED | Toolkit\Traits\HasAvatar |
| `Support\Traits\Models\HasFormatters` | MIGRATED | Toolkit\Traits\HasFormatters |
| `Support\Traits\RunsConditionally` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Utilities\Auth` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\BladeDirectives` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\DatabaseSession` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\Runners\ConditionalRunner` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\DiskSpaceValidator` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\Environment` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\RequirementsChecker` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\Username` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
