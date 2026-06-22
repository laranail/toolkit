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
| **MIGRATED** | 86 | Carried into `laranail/toolkit` (often renamed — `…DTO`/`…Facade`/`…Resource` suffixes dropped in the flat layout). Includes 20 **MIGRATED(merged)** symbols folded into an existing class (the short name changes — see note): the original 5, the **14 Carbon holiday/date calendar traits** folded into `Macros\CarbonMacros` in G3, plus `Support\Utilities\BladeDirectives` folded into `Providers\BladeServiceProvider` in G4. |
| **RELOCATED** | 17 | Moved to a sibling package: **16** notification classes → `laranail/notifications`, the `NotificationChannel` enum → same. |
| **DROPPED** | 176 | Not carried over — native-duplicative, consolidated, or out-of-scope. See the buckets below. |
| **Total** | 279 | |

> **MIGRATED(merged)** — these legacy symbols were *folded into* an existing
> toolkit class rather than ported 1:1, so their short name disappears.
> Because `ApiSurfaceTest` matches by short name, these keep a
> `removed-symbols.json` entry with `status: "merged"` + a `target` (any
> allowlist entry counts as accounted-for). The original five (G2-3):
> `CacheService` / `CacheServiceInterface` → `Utilities\CachingUtil`;
> `StringHelperService` / `StringHelperServiceInterface` → `Helpers\XHelper`;
> `Support\Utilities\Username` → `Traits\HasFormatters` (name→username, pheg
> inlined to native `Str`). Plus the **14 Carbon holiday/date calendar traits**
> (G3) — `MultiNationalDates`, `BrazilianHolidays`, `CanadianDates`,
> `DutchHolidays`, `FrenchHolidays`, `GermanHolidays`, `IndianHolidays`,
> `IndonesianHolidays`, `ItalianHolidays`, `KenyanHolidays`, `SwedishHolidays`,
> `UkrainianHolidays`, `UsDates`, `ZambianHolidays` → folded into
> `Macros\CarbonMacros` as registered Carbon macros (assignment bugs fixed).
> Plus (G4) `Support\Utilities\BladeDirectives` — its portable custom directives
> were folded into `Providers\BladeServiceProvider` (the short `BladeDirectives`
> class is gone). Native-duplicative (`@dump`/`@dd`/`@pushOnce`), Mix-removed
> (`@mix`), compile-time-broken (`@kebab`/`@snake`/`@camel`/`@count`) and the
> missing-helper `@javascript` directives were dropped; value-echoing ports
> (`@nl2br`, `@dataAttributes`, `@inputvalue`) were XSS-hardened with `e()`.

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
| `Laravel\Macros\*` | 90 | The 107-file micro-macro library **consolidated** into the grouped `Macros\{Str,Arr,Collection,QueryBuilder,Blueprint,Request,Carbon}Macros` providers (kept subset). **G3 added the Carbon group**: the 14 national holiday/date calendar traits (`MultiNationalDates`, `BrazilianHolidays`, `CanadianDates`, `DutchHolidays`, `FrenchHolidays`, `GermanHolidays`, `IndianHolidays`, `IndonesianHolidays`, `ItalianHolidays`, `KenyanHolidays`, `SwedishHolidays`, `UkrainianHolidays`, `UsDates`, `ZambianHolidays`) were ported into `Macros\CarbonMacros` (110 holiday predicates + 7 non-native date helpers), with the legacy `=`-instead-of-`===` assignment bugs fixed. `DistanceBetween` (orphaned + pheg) and `ParallelMap` (amphp) stay dropped. Coverage asserted by the macro-inventory + Carbon behaviour tests. |
| `Foundation\Services\*` + `Foundation\Contracts\*` | ~34 | The service-locator service layer (`CacheService`, `FileService`, `ValidationService`, `SessionService`, `SystemService`, helper services, …) — **native-duplicative**. Superseded by native Laravel + the kept `Utilities\*` / `Traits\*`. These were the services the old `Laranail` facade fronted (see below). **Revived** (hardened, de-faceted, bound by contract): `RouteService`, `HttpConfigurationService`, `DatabaseService`, `ModelService` + their contracts → `Toolkit\Services\*` (G2-2). |
| `Laravel\Providers\*` | 10 | Per-macro sub-providers + `MacrosServiceProvider` → consolidated into `Macros\MacroServiceProvider`; the middleware provider dropped (register middleware in the app). |
| `Laravel\Http\*` | 3 | Of the 7 legacy HTTP symbols, **5 were MIGRATED in G1** (`ApiMiddleware`, `ApiRequestMiddleware`, `ApiResponseMiddleware` → `Http\Middleware\*`; `BaseRequest` → `Http\Requests\BaseRequest`; `Support\Contracts\ShovelHttpInterface` → `Http\Contracts\ShovelHttpInterface`). Still dropped: `BaseController` (→ `Http\Controllers\CrudController`), `ApiRequest` (the camelCase-keyed `BaseRequest` subclass is trivially re-derivable; envelope its errors via `Traits\ApiResponseTrait`), and `EmailObfuscatorMiddleware` (pheg dependency). |
| `Shared\Exceptions\*` + `Foundation\Exceptions\*` | 10 | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, `ModelNotFoundException`). |
| `Support\Traits\*` | 4 | `HasAuth`/`HasLivewire`/`HasPackageTools`/`HasErrorStorage` — native Laravel or out of the toolkit's scope (livewire/package-tools/pheg). (`HasGuzzleConfig` **revived** alongside `HttpConfigurationService` → `Toolkit\Traits\HasGuzzleConfig`.) |
| `Support\Contracts\*` | 5 | Interfaces for the dropped service-locator services. (`ShovelHttpInterface` was **MIGRATED in G1** → `Http\Contracts\ShovelHttpInterface`, backing `ApiResponseMiddleware`.) |
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
| `Foundation\Contracts\CacheServiceInterface` | MIGRATED(merged) | Folded into `Toolkit\Utilities\CachingUtil` (remember/rememberForever/put/many/increment/decrement + fluent tags + key namespacing + log-and-fall-back). No separate cache contract kept; `CachingUtil` is bound by class. |
| `Foundation\Contracts\DatabaseServiceInterface` | MIGRATED | Toolkit\Services\Contracts\DatabaseServiceInterface |
| `Foundation\Contracts\FileServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\RouteServiceInterface` | MIGRATED | Toolkit\Services\Contracts\RouteServiceInterface |
| `Foundation\Contracts\Services\AuthenticationHelperServiceInterface` | MIGRATED | Toolkit\Services\Contracts\AuthenticationHelperServiceInterface |
| `Foundation\Contracts\Services\ClassHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\CollectionHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\DatabaseFileServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\ErrorStorageServiceInterface` | MIGRATED | Toolkit\Services\Contracts\ErrorStorageServiceInterface |
| `Foundation\Contracts\Services\FakerHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\FileHelperServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\HttpConfigurationServiceInterface` | MIGRATED | Toolkit\Services\Contracts\HttpConfigurationServiceInterface |
| `Foundation\Contracts\Services\LivewireComponentServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\ModelFormatterServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\PackageServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\StringHelperServiceInterface` | MIGRATED(merged) | Folded into `Toolkit\Helpers\XHelper` (`ucWords`, `usernameFromEmail`, `emailFromUsername`). |
| `Foundation\Contracts\SessionServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\SystemServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\UtilityServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\ValidationServiceInterface` | MIGRATED | `Toolkit\Services\Contracts\ValidationServiceInterface` (DB-credential methods dropped — see ValidationService row). |
| `Foundation\Exceptions\FileTooLargeException` | MIGRATED | Toolkit\Exceptions\FileTooLargeException |
| `Foundation\Exceptions\InvalidPathException` | MIGRATED | Toolkit\Exceptions\InvalidPathException |
| `Foundation\Exceptions\LaranailException` | MIGRATED | Toolkit\Exceptions\LaranailException |
| `Foundation\Exceptions\LaranailExceptionHandler` | DROPPED | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, model `ModelNotFoundException`). |
| `Foundation\Facades\LaranailFacade` | DROPPED | The 48-method service-locator facade — see **The `Laranail` facade** section. Replaced by per-module facade aliases + the new fluent `Toolkit` facade (R-Facade). |
| `Foundation\Laranail` | DROPPED | The 48-method service-locator facade — see **The `Laranail` facade** section. Replaced by per-module facade aliases + the new fluent `Toolkit` facade (R-Facade). |
| `Foundation\Providers\LaranailEventServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Providers\LaranailHookServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Providers\LaranailServiceProvider` | DROPPED | Superseded by `Providers\ToolkitServiceProvider` (merge/publish/migrations) + native event/listener auto-discovery. |
| `Foundation\Services\AuthenticationHelperService` | MIGRATED | Toolkit\Services\AuthenticationHelperService |
| `Foundation\Services\AuthenticationService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\CacheService` | MIGRATED(merged) | Folded into `Toolkit\Utilities\CachingUtil` — existing `cache`/`get`/`forget` API preserved verbatim; legacy delta added as new methods. |
| `Foundation\Services\ClassHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\CollectionHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\DatabaseFileService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\DatabaseService` | MIGRATED | Toolkit\Services\DatabaseService (path-confined cleanup) |
| `Foundation\Services\ErrorStorageService` | MIGRATED | Toolkit\Services\ErrorStorageService |
| `Foundation\Services\FakerHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\FileHelperService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\FileService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\HttpConfigurationService` | MIGRATED | Toolkit\Services\HttpConfigurationService (reads laranail.toolkit.http.*) |
| `Foundation\Services\LivewireComponentService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ModelFormatterService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ModelService` | MIGRATED | Toolkit\Services\ModelService (schema-validated + grammar-quoted raw SQL) |
| `Foundation\Services\PackageService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\RouteService` | MIGRATED | Toolkit\Services\RouteService |
| `Foundation\Services\SessionService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\StringHelperService` | MIGRATED(merged) | Folded into `Toolkit\Helpers\XHelper` (`ucWords`, `usernameFromEmail`, `emailFromUsername`) — de-faceted to static helpers. |
| `Foundation\Services\SystemService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\UtilityService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\ValidationService` | MIGRATED | `Toolkit\Services\ValidationService` (hardened, de-faceted, bound by contract). Error-bag HTML, conditional CSS classes, checkbox status, old-input resolution ported; **every** interpolated value `e()`-escaped + returned as `HtmlString`. **DROPPED-with-reason:** `isValidDbCredentials` / `setDatabaseCredentials` — the legacy pair mutated live `config()` globally and ran a raw `SHOW TABLES`; rebuilding it safely (isolated on-demand connection) is a different, credential-leak-prone feature for negligible benefit. The reachability check `isValidDatabaseConnection()` (via `Schema::hasTable`) is kept. |
| `Laravel\Commands\AssetCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\CronJobCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\DatabaseCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\InitializeApplication` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\LicenseCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\MacrosCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\MaintenanceCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\SetAppNamespace` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Commands\TidyCommand` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Http\Controllers\BaseController` | DROPPED | Replaced by `Http\Controllers\CrudController` + `Traits\ApiResponseTrait`. |
| `Laravel\Http\Middleware\ApiMiddleware` | MIGRATED | Toolkit\Http\Middleware\ApiMiddleware (abstract base; the recursive key walker extracted into the reusable `Http\Concerns\MutatesPayloadKeys` concern). |
| `Laravel\Http\Middleware\ApiRequestMiddleware` | MIGRATED | Toolkit\Http\Middleware\ApiRequestMiddleware (alias `api.request`; snake_cases incoming keys). |
| `Laravel\Http\Middleware\ApiResponseMiddleware` | MIGRATED | Toolkit\Http\Middleware\ApiResponseMiddleware (alias `api.response`; envelope shape-compatible with `Traits\ApiResponseTrait`, camelCases data keys, hardened json_decode). |
| `Laravel\Http\Middleware\EmailObfuscatorMiddleware` | DROPPED | pheg dependency (`pheg()->email()->obfuscate()`) is out of scope; see dropped.md. |
| `Laravel\Http\Requests\ApiRequest` | DROPPED | The legacy `BaseRequest` subclass only swapped the failed-validation response to a JSON body; build that envelope with `Traits\ApiResponseTrait` (or override `failedValidation()` on a `BaseRequest` subclass). |
| `Laravel\Http\Requests\BaseRequest` | MIGRATED | Toolkit\Http\Requests\BaseRequest (input sanitization; Unicode-safe name normaliser; legacy discard-of-return bug fixed). |
| `Laravel\Jobs\BaseJob` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Listeners\BaseListener` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Listeners\LicenseListener` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Laravel\Macros\After` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\At` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Before` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Bind` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\BrazilianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\CanadianDates` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\CapitalizeWords` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CatchableProxy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ChunkBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CollectBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Decrement` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\DistanceBetween` | DROPPED | Orphaned pheg-dependent geo invokable; never wired to any Macroable target. Not ported (no clean target class) (G3). |
| `Laravel\Macros\DutchHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
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
| `Laravel\Macros\FrenchHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\FromBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromJson` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromPairs` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GenerateName` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GermanHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
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
| `Laravel\Macros\IndianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\IndonesianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Initials` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfter` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfterKey` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAt` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertBefore` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertBeforeKey` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Interpolate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IsEquals` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ItalianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\KenyanHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Krsort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Ksort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\LinesCount` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\MacroSupport` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Matches` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Message` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\MultiNationalDates` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Ninth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\None` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Paginate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PaginateFirstDifferent` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\PaginateWithPrevious` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Paginator` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ParallelMap` | DROPPED | Requires `amphp/parallel-functions`; native Laravel concurrency supersedes it (G3). |
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
| `Laravel\Macros\SwedishHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Tail` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Tenth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Third` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToPairs` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Transpose` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\TryCatch` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\UkrainianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\UsDates` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Validate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhenEquals` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereContains` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereEndsWith` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WhereStartsWith` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WithSize` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\WordsCount` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ZambianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
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
| `Shared\Exceptions\CollectionItemNotFound` | MIGRATED | Toolkit\Exceptions\CollectionItemNotFound |
| `Shared\Exceptions\ImmutableDataException` | MIGRATED | Toolkit\Exceptions\ImmutableDataException |
| `Shared\Exceptions\MissingUuidColumnException` | MIGRATED | Toolkit\Exceptions\MissingUuidColumnException |
| `Shared\Exceptions\ModelException` | MIGRATED | Toolkit\Exceptions\ModelException |
| `Shared\Exceptions\UuidException` | MIGRATED | Toolkit\Exceptions\UuidException |
| `Support\Contracts\CacheHelperInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\LoggerServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ResponseBuilderServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ResponseMacroInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Contracts\ShovelHttpInterface` | MIGRATED | Toolkit\Http\Contracts\ShovelHttpInterface (HTTP status code constants + reason-phrase map; backs `ApiResponseMiddleware`). |
| `Support\Contracts\SystemServiceInterface` | DROPPED | Interfaces for dropped service-locator services; gone with their implementations. |
| `Support\Eloquent\Scopes\ArchiveScope` | MIGRATED | Toolkit\Support\Scopes\ArchiveScope |
| `Support\Facades\LanguagesFacade` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Resources\AvatarGenerationResource` | MIGRATED | Toolkit\Modules\Avatar\AvatarGeneration |
| `Support\Resources\AvatarResolutionResource` | MIGRATED | Toolkit\Modules\Avatar\AvatarResolution |
| `Support\Resources\GravatarResolutionResource` | MIGRATED | Toolkit\Modules\Gravatar\GravatarResolution |
| `Support\Services\Atlas\Countries` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Services\Atlas\Languages` | DROPPED | Not carried over; superseded by native Laravel or the kept toolkit surface. |
| `Support\Traits\HasArchiver` | MIGRATED | Toolkit\Traits\HasArchiver |
| `Support\Traits\HasAuth` | MIGRATED | Toolkit\Traits\HasAuth |
| `Support\Traits\HasErrorStorage` | MIGRATED | Toolkit\Traits\HasErrorStorage |
| `Support\Traits\HasGuzzleConfig` | MIGRATED | Toolkit\Traits\HasGuzzleConfig (delegates to HttpConfigurationService) |
| `Support\Traits\HasPackageTools` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\Livewire\HasLivewire` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Traits\Models\HasAvatar` | MIGRATED | Toolkit\Traits\HasAvatar |
| `Support\Traits\Models\HasFormatters` | MIGRATED | Toolkit\Traits\HasFormatters |
| `Support\Traits\RunsConditionally` | DROPPED | Service-specific/out-of-scope traits (auth, livewire, guzzle-config, package-tools, error-storage) — native Laravel or out of the toolkit's scope. |
| `Support\Utilities\Auth` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\BladeDirectives` | MIGRATED(merged) | Portable custom directives folded into `Toolkit\Providers\BladeServiceProvider` (G4): `@addstyle`/`@addscript`/`@inline`/`@dataAttributes`/`@haserror`/`@nl2br`/`@returnifempty`/`@selectedif`/`@inputvalue`/`@optionvalue`/`@checkboxvalue`/`@checkboxvaluefromarray`. Native-duplicative/broken directives dropped (`@dump`,`@dd`,`@pushonce`,`@mix`,`@kebab`/`@snake`/`@camel`,`@count`,`@javascript`); value-echoing ports XSS-hardened with `e()`; legacy bugs fixed (`endscript`→`</style>`, `@inline` missing return). |
| `Support\Utilities\DatabaseSession` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\Runners\ConditionalRunner` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\DiskSpaceValidator` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\Environment` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\RequirementsChecker` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\Username` | MIGRATED(merged) | Name→username suggestion folded into `Toolkit\Traits\HasFormatters::suggestUsername()` + the native generator `XHelper::nameToUsernames()`. The legacy `pheg()->name()->name2username()` dependency was **inlined to native `Str`** (slug/substr-based candidates); availability checked via the model's own query. |
