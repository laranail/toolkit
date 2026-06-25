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
| **MIGRATED** | 109 | Carried into `laranail/toolkit` (often renamed — `…DTO`/`…Facade`/`…Resource` suffixes dropped in the flat layout). Includes 42 **MIGRATED(merged)** symbols folded into an existing class (the short name changes — see note): the original 5, the **14 Carbon holiday/date calendar traits** folded into `Macros\CarbonMacros` in G3, `Support\Utilities\BladeDirectives` folded into `Providers\BladeServiceProvider` in G4, and the **22 G6 folds** — 17 Collection/Arr/Str micro-macros into the grouped macro providers, plus `SystemIO\Environment`→`Utilities\EnvironmentUtil`, `SystemIO\RequirementsChecker`→`Support\Diagnostics\RequirementsDiagnostics`, `Support\Utilities\Auth`→`Utilities\AuthUtil`, and `Foundation\Services\ModelFormatterService` (+ its contract)→`Traits\HasFormatters`. `DatabaseSession` is a plain MIGRATED (ported under the same short name to `Support\Models\`). |
| **RELOCATED** | 17 | Moved to a sibling package: **16** notification classes → `laranail/notifications`, the `NotificationChannel` enum → same. |
| **DROPPED** | 153 | Not carried over — native-duplicative, consolidated, or out-of-scope. See the buckets below. |
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

> **G8 (2026-06-23) — the owner's restoration notes, RESOLVED.** A developer toolkit keeps
> convenient quick-access wrappers, so the items below were **restored/enhanced** (native Laravel
> under the hood, no duplication). Full checklist in `RESTORE-CANDIDATES.md`; final statuses are in
> the generated per-symbol table at the end of this file.
> - **DistanceBetween** → restored natively as `Helpers\GeoHelper::distanceBetween` (Haversine, no pheg).
>   All `Laravel\Macros\*` re-verified: every macro is registered in a grouped provider or in
>   `Macros\CarbonMacros` (14 national calendars + date helpers, the `=`/`===`/octal bugs fixed) — **none lost**.
> - **Helper services** (`Session`/`System`/`Class`/`Collection`/`Utility`/`Faker`/`Authentication`) → their
>   useful convenience methods folded into `Helpers\{XHelper,SystemHelper,DbHelper,GeoHelper,ConsoleHelper}`,
>   `Services\SessionService` (injectable), `Utilities\{LoggingUtil,AuthUtil}`, `Services\ModelService`, and `Macros\CollectionMacros`.
>   `CacheService`/`FileService`/`ValidationService` were already covered (`CachingUtil` / `FileHelper`+`FilePathGuard` / `Services\ValidationService`).
>   The **unsafe** DB-credential mutation became the safe `Helpers\DbHelper::canConnectWith()` (ephemeral connection, no `config()` mutation, no credential logging).
> - **BaseController / ApiRequest / EmailObfuscatorMiddleware** → all restored. `BaseController` is now a
>   reusable abstract base (`AuthorizesRequests` + `ValidatesRequests` + `Traits\ApiResponseTrait`) that
>   `Http\Controllers\CrudController` extends; `Http\Requests\ApiRequest` (JSON-envelope validation failures)
>   extends `BaseRequest`; `Http\Middleware\EmailObfuscatorMiddleware` rebuilt **natively** (no pheg, opt-in alias `email.obfuscate`).
> - **Reusable base classes** added for future reuse: `Jobs\BaseJob`, `Listeners\BaseListener`,
>   `Observers\BaseObserver`, `Events\BaseEvent` (real shared code, not stubs).
> - **Traits:** `HasAuth` and `HasErrorStorage` are already MIGRATED + improved (`Traits\*`).
>   `HasPackageTools` (ServiceProvider / `laranail/package-tools` concern) and `HasLivewire` (Livewire-specific)
>   stay out of the core toolkit's scope.



| Bucket | ~count | Why |
|---|---:|---|
| `Laravel\Macros\*` | 90 | The 107-file micro-macro library **consolidated** into the grouped `Macros\{Str,Arr,Collection,QueryBuilder,Blueprint,Request,Carbon}Macros` providers (kept subset). **G3 added the Carbon group**: the 14 national holiday/date calendar traits (`MultiNationalDates`, `BrazilianHolidays`, `CanadianDates`, `DutchHolidays`, `FrenchHolidays`, `GermanHolidays`, `IndianHolidays`, `IndonesianHolidays`, `ItalianHolidays`, `KenyanHolidays`, `SwedishHolidays`, `UkrainianHolidays`, `UsDates`, `ZambianHolidays`) were ported into `Macros\CarbonMacros` (110 holiday predicates + 7 non-native date helpers), with the legacy `=`-instead-of-`===` assignment bugs fixed. `DistanceBetween` (orphaned + pheg) and `ParallelMap` (amphp) stay dropped. Coverage asserted by the macro-inventory + Carbon behaviour tests. |
| `Foundation\Services\*` + `Foundation\Contracts\*` | ~34 | The service-locator service layer (`CacheService`, `FileService`, `ValidationService`, `SessionService`, `SystemService`, helper services, …) — **native-duplicative**. Superseded by native Laravel + the kept `Utilities\*` / `Traits\*`. These were the services the old `Laranail` facade fronted (see below). **Revived** (hardened, de-faceted, bound by contract): `RouteService`, `HttpConfigurationService`, `DatabaseService`, `ModelService` + their contracts → `Toolkit\Services\*` (G2-2). **G8** folded the remaining useful convenience methods (`Session`/`System`/`Class`/`Collection`/`Faker`/`Authentication` services) into `Helpers\*` / `Utilities\*` / `Macros\CollectionMacros` — see the G8 note above. |
| `Laravel\Providers\*` | 10 | Per-macro sub-providers + `MacrosServiceProvider` → consolidated into `Macros\MacroServiceProvider`; the middleware provider dropped (register middleware in the app). |
| `Laravel\Http\*` | 0 | **All 7 legacy HTTP symbols now MIGRATED.** G1: `ApiMiddleware`/`ApiRequestMiddleware`/`ApiResponseMiddleware` → `Http\Middleware\*`, `BaseRequest` → `Http\Requests\BaseRequest`, `ShovelHttpInterface` → `Http\Contracts\ShovelHttpInterface`. **G8: `BaseController`** is now the reusable abstract base `CrudController` extends; **`ApiRequest`** → `Http\Requests\ApiRequest` (JSON-envelope failures); **`EmailObfuscatorMiddleware`** rebuilt natively (no pheg, alias `email.obfuscate`). |
| `Shared\Exceptions\*` + `Foundation\Exceptions\*` | 10 | Use native SPL / Laravel exceptions (`InvalidArgumentException`, `RuntimeException`, `ModelNotFoundException`). |
| `Support\Traits\*` | 2 | `HasAuth` and `HasErrorStorage` are **MIGRATED + improved** → `Toolkit\Traits\*` (memoised, strict-typed). Still dropped: `HasLivewire` (Livewire-specific) and `HasPackageTools` (ServiceProvider / `laranail/package-tools` concern). (`HasGuzzleConfig` **revived** alongside `HttpConfigurationService` → `Toolkit\Traits\HasGuzzleConfig`.) |
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

> **G8 — the owner's "good helper functions" list, resolved (each now lives in the toolkit, native under the hood):**
> | Legacy fn(s) | Now provided by |
> |---|---|
> | getAppUrl, getCurrentRouteInfo, isCurrentRoute, getActiveCssClassForRoute | `Services\RouteService` |
> | getErrorBagMessage, getCheckboxStatus, isValidDatabaseConnection | `Services\ValidationService` |
> | registerModelObserver, eloquent2selectbox, sortItemWithChildren, getModelItem | `Services\ModelService` |
> | existsInFilterKey, joinInFilterKey, removeFromFilterKey, saveJavaScriptCookies | `Services\SessionService` (injectable, `Toolkit::session()`) |
> | getComposerArray→`composer`, getSystemEnv→`systemInfo`, getServerEnv→`serverEnv`, isSslInstalled | `Helpers\SystemHelper` (G8) |
> | environment | `Utilities\EnvironmentUtil` |
> | arrayToDotNotation, html→`escapeHtml`, ucWords, generateUsername→`usernameFromEmail`, generateEmailFromUsername→`emailFromUsername`, getClassNameFromClass→`classBasename`, random→`randomIntExcept`, faker | `Helpers\XHelper` (G8) |
> | mapKeyValuePairArray→`mapKeyValuePairs`, sortSearchResults | `Macros\CollectionMacros` (G8) |
> | username, authHelper, isUserExists→`userExists` | `Utilities\AuthUtil` (G8) |
> | httpConfig | `Services\HttpConfigurationService` + `Traits\HasGuzzleConfig` |
> | formatter | `Traits\HasFormatters` |
> | logError | `Utilities\LoggingUtil::exception/error` (G8) |
> | writeToConsoleOutput | `Helpers\ConsoleHelper::write` (G8) |
> | setDatabaseCredentials | **dropped (unsafe config mutation)** → safe `Helpers\DbHelper::canConnectWith` |
> | generateLivewireComponentKey, livewire | **dropped** — Livewire-specific, out of the core toolkit's scope |

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
| `Foundation\Contracts\Services\ModelFormatterServiceInterface` | MIGRATED(merged) | Contract dropped; the working formatters live on `Traits\HasFormatters` (G6d). |
| `Foundation\Contracts\Services\PackageServiceInterface` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Contracts\Services\StringHelperServiceInterface` | MIGRATED(merged) | Folded into `Toolkit\Helpers\XHelper` (`ucWords`, `usernameFromEmail`, `emailFromUsername`). |
| `Foundation\Contracts\SessionServiceInterface` | MIGRATED | Revived as the injectable `Toolkit\Services\Contracts\SessionServiceInterface` (filter-key + JS-cookie helpers), implemented by `Toolkit\Services\SessionService` and fronted by `Toolkit::session()`. |
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
| `Foundation\Services\ModelFormatterService` | MIGRATED(merged) | Working formatters (content/address) folded into `Traits\HasFormatters` via native Carbon/Str; stub methods that returned `''` dropped (G6d). |
| `Foundation\Services\ModelService` | MIGRATED | Toolkit\Services\ModelService (schema-validated + grammar-quoted raw SQL) |
| `Foundation\Services\PackageService` | DROPPED | Native-duplicative service layer (fronted by the old `Laranail` facade); superseded by native Laravel + the kept `Utilities\*`. PackageService/Username/Auth/DatabaseSession/ModelFormatter cited in `dropped.md`. |
| `Foundation\Services\RouteService` | MIGRATED | Toolkit\Services\RouteService |
| `Foundation\Services\SessionService` | MIGRATED | Revived as the injectable `Toolkit\Services\SessionService` (filter-key + JS-cookie helpers, session/cookie writes via injected store + jar), bound by `SessionServiceInterface` and fronted by `Toolkit::session()`. |
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
| `Laravel\Macros\Before` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Bind` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\BrazilianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\CanadianDates` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\CapitalizeWords` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\CatchableProxy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ChunkBy` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\CollectBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Decrement` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\DistanceBetween` | DROPPED | Orphaned pheg-dependent geo invokable; never wired to any Macroable target. Not ported (no clean target class) (G3). |
| `Laravel\Macros\DutchHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\EachCons` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Eighth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Error` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Extract` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\FactoryBuilderMixin` | MIGRATED | Toolkit\Macros\FactoryBuilderMixin |
| `Laravel\Macros\Fifth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FilterMap` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstDifferentLengthAwarePaginator` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstOrFail` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FirstOrPush` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\ForSelectBox` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Fourth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FrenchHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\FromBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromJson` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\FromPairs` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\GenerateName` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GermanHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\GetFile` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GetNth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Glob` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\GroupByModel` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Head` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\HighlightWords` | MIGRATED(merged) | Folded into `Macros\StringMacros` as a Str/Stringable macro (G6a); emits e()-escaped HtmlString. |
| `Laravel\Macros\Human` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IfAny` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IfEmpty` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\IfMacro` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Increment` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\IndianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\IndonesianHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Initials` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfter` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAfterKey` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\InsertAt` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
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
| `Laravel\Macros\ReadingMinutes` | MIGRATED(merged) | Folded into `Macros\StringMacros` as a Str/Stringable macro (G6a). |
| `Laravel\Macros\Recursive` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\RenameKeys` | MIGRATED(merged) | Folded into `Macros\ArrMacros` as the `Arr::renameKeys` multi-rename macro (G6a). |
| `Laravel\Macros\ReplaceInKeys` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ResponseMacros` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Rotate` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Round5` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Rsort` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Second` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SectionBy` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Seventh` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SimplePaginate` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Sixth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SliceBefore` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\StripTags` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Success` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\SwedishHolidays` | MIGRATED(merged) | Ported into `Macros\CarbonMacros` as registered Carbon macros (G3); legacy `=`/`===` assignment bugs fixed. Coverage in the macro-inventory + Carbon behaviour tests. |
| `Laravel\Macros\Tail` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
| `Laravel\Macros\Tenth` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\Third` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToBase64` | DROPPED | Consolidated into the grouped `Macros\*Macros` providers (kept subset) or dropped as low-value (holiday/date macros); see the macro inventory test. |
| `Laravel\Macros\ToPairs` | MIGRATED(merged) | Folded into `Macros\CollectionMacros` as a registered Collection macro (G6a). |
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
| `Support\Utilities\Auth` | MIGRATED(merged) | Ported to `Utilities\AuthUtil` (typed per-guard accessor; renamed to avoid the `Auth` facade collision) (G6d). |
| `Support\Utilities\BladeDirectives` | MIGRATED(merged) | Portable custom directives folded into `Toolkit\Providers\BladeServiceProvider` (G4): `@addstyle`/`@addscript`/`@inline`/`@dataAttributes`/`@haserror`/`@nl2br`/`@returnifempty`/`@selectedif`/`@inputvalue`/`@optionvalue`/`@checkboxvalue`/`@checkboxvaluefromarray`. Native-duplicative/broken directives dropped (`@dump`,`@dd`,`@pushonce`,`@mix`,`@kebab`/`@snake`/`@camel`,`@count`,`@javascript`); value-echoing ports XSS-hardened with `e()`; legacy bugs fixed (`endscript`→`</style>`, `@inline` missing return). |
| `Support\Utilities\DatabaseSession` | MIGRATED | Ported to `Support\Models\DatabaseSession` (read model over the `sessions` table; no migration shipped) (G6d). |
| `Support\Utilities\Runners\ConditionalRunner` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\DiskSpaceValidator` | DROPPED | Native replacement (Blade directives are native; `Environment` via native helpers) — cited in `dropped.md`. |
| `Support\Utilities\SystemIO\Environment` | MIGRATED(merged) | Ported to `Utilities\EnvironmentUtil` (static predicates over the app's environment resolver) (G6b). |
| `Support\Utilities\SystemIO\RequirementsChecker` | MIGRATED(merged) | Useful probes (extensions, writable dirs, disk space) folded into `Support\Diagnostics\RequirementsDiagnostics`; apache/nested-config probes dropped (G6c). |
| `Support\Utilities\Username` | MIGRATED(merged) | Name→username suggestion folded into `Toolkit\Traits\HasFormatters::suggestUsername()` + the native generator `XHelper::nameToUsernames()`. The legacy `pheg()->name()->name2username()` dependency was **inlined to native `Str`** (slug/substr-based candidates); availability checked via the model's own query. |

<!-- LEDGER:START (generated by tests/Fixtures/Legacy/build-ledger.php — do not hand-edit) -->
## Verified per-namespace ledger (generated)

Mechanically resolved old→new by actual presence in the current code. Regenerate
with `php tests/Fixtures/Legacy/build-ledger.php`; gate with `--verify`.

| Status | Count | Note |
|---|---:|---|
| **MIGRATED** | 173 | direct + 82 merged |
| **RELOCATED** | 17 | → laranail/notifications |
| **DROPPED** | 89 | native / out-of-scope (see rows) |
| **Total** | 279 | |

### Simtabi\Laranail\Features\Archiver\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `ArchiverServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceInterface` |

### Simtabi\Laranail\Features\Archiver\Facades

| Legacy type | Status | New target / reason |
|---|---|---|
| `ArchiverFacade` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\Archiver` |

### Simtabi\Laranail\Features\Archiver\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `ArchiveManager` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiveManager` |
| `ArchiverService` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverService` |
| `Extractor` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\Extractor` |
| `Tar` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\Tar` |
| `TarGz` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\TarGz` |
| `Zip` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\Zip` |

### Simtabi\Laranail\Features\Avatar\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarServiceInterface` |

### Simtabi\Laranail\Features\Avatar\DTOs

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarGenerationDTO` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarGeneration` |
| `AvatarResolutionContextDTO` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolutionContext` |
| `AvatarResolutionDTO` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolution` |

### Simtabi\Laranail\Features\Avatar\Facades

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarFacade` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\Avatar` |

### Simtabi\Laranail\Features\Avatar\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarResolutionContext` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolutionContext` |
| `AvatarResolver` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolver` |
| `AvatarService` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarService` |

### Simtabi\Laranail\Features\Captcha\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `CaptchaProviderInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface` |

### Simtabi\Laranail\Features\Captcha\Providers

| Legacy type | Status | New target / reason |
|---|---|---|
| `HcaptchaProvider` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\HcaptchaProvider` |
| `RecaptchaProvider` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\RecaptchaProvider` |
| `TurnstileProvider` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\TurnstileProvider` |

### Simtabi\Laranail\Features\Captcha\Results

| Legacy type | Status | New target / reason |
|---|---|---|
| `CaptchaVerificationResult` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult` |

### Simtabi\Laranail\Features\Captcha\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `CaptchaService` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaService` |

### Simtabi\Laranail\Features\Gravatar\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `GravatarServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarServiceInterface` |

### Simtabi\Laranail\Features\Gravatar\DTOs

| Legacy type | Status | New target / reason |
|---|---|---|
| `GravatarResolutionDTO` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarResolution` |

### Simtabi\Laranail\Features\Gravatar\Facades

| Legacy type | Status | New target / reason |
|---|---|---|
| `GravatarFacade` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Gravatar\Gravatar` |

### Simtabi\Laranail\Features\Gravatar\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `GravatarService` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarService` |

### Simtabi\Laranail\Features\Notifications\Channels

| Legacy type | Status | New target / reason |
|---|---|---|
| `AbstractNotificationChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\AbstractNotificationChannel` |
| `AppleBusinessMessagesChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\AppleBusinessMessagesChannel` |
| `CacheChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\CacheChannel` |
| `ConsoleChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\ConsoleChannel` |
| `DatabaseChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\DatabaseChannel` |
| `DiscordChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\DiscordChannel` |
| `EmailChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\EmailChannel` |
| `FileChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\FileChannel` |
| `LogChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\LogChannel` |
| `PushChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\PushChannel` |
| `SlackChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\SlackChannel` |
| `SmsChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\SmsChannel` |
| `WebhookChannel` | RELOCATED | `Simtabi\Laranail\Notifications\Channels\WebhookChannel` |

### Simtabi\Laranail\Features\Notifications\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `NotificationChannelInterface` | RELOCATED | `Simtabi\Laranail\Notifications\Contracts\NotificationChannelInterface` |

### Simtabi\Laranail\Features\Notifications\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `NotificationService` | RELOCATED | `Simtabi\Laranail\Notifications\Services\NotificationService` |

### Simtabi\Laranail\Features\Notifications\Support

| Legacy type | Status | New target / reason |
|---|---|---|
| `NotificationResult` | RELOCATED | `Simtabi\Laranail\Notifications\Support\NotificationResult` |

### Simtabi\Laranail\Foundation

| Legacy type | Status | New target / reason |
|---|---|---|
| `Laranail` | MIGRATED | `Simtabi\Laranail\Toolkit\Facades\Laranail` |

### Simtabi\Laranail\Foundation\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `AuthenticationServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CacheServiceInterface` | MERGED | `Simtabi\Laranail\Toolkit\Utilities\CachingUtil` |
| `DatabaseServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\DatabaseServiceInterface` |
| `FileServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface` |
| `RouteServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface` |
| `SessionServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface` |
| `SystemServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface` |
| `UtilityServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ValidationServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\ValidationServiceInterface` |

### Simtabi\Laranail\Foundation\Contracts\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `AuthenticationHelperServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\AuthenticationHelperServiceInterface` |
| `ClassHelperServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CollectionHelperServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `DatabaseFileServiceInterface` | MERGED | `Merged into Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface (validate/validateSize) + DatabaseService (the .sql import side).` |
| `ErrorStorageServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\ErrorStorageServiceInterface` |
| `FakerHelperServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FileHelperServiceInterface` | MERGED | `Merged into Simtabi\Laranail\Toolkit\Services\Contracts\FileServiceInterface.` |
| `HttpConfigurationServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface` |
| `LivewireComponentServiceInterface` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface` |
| `ModelFormatterServiceInterface` | MERGED | `Simtabi\Laranail\Toolkit\Traits\HasFormatters (working formatters folded, G6d)` |
| `PackageServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `StringHelperServiceInterface` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper` |

### Simtabi\Laranail\Foundation\Exceptions

| Legacy type | Status | New target / reason |
|---|---|---|
| `FileTooLargeException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\FileTooLargeException` |
| `InvalidPathException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\InvalidPathException` |
| `LaranailException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\LaranailException` |
| `LaranailExceptionHandler` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |

### Simtabi\Laranail\Foundation\Facades

| Legacy type | Status | New target / reason |
|---|---|---|
| `LaranailFacade` | MIGRATED | `Simtabi\Laranail\Toolkit\Facades\Laranail` |

### Simtabi\Laranail\Foundation\Providers

| Legacy type | Status | New target / reason |
|---|---|---|
| `LaranailEventServiceProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LaranailHookServiceProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LaranailServiceProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |

### Simtabi\Laranail\Foundation\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `AuthenticationHelperService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\AuthenticationHelperService` |
| `AuthenticationService` | MERGED | `Simtabi\Laranail\Toolkit\Utilities\AuthUtil (auth context + username/userExists folded, G8a)` |
| `CacheService` | MERGED | `Simtabi\Laranail\Toolkit\Utilities\CachingUtil` |
| `ClassHelperService` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (classBasename folded, G8a)` |
| `CollectionHelperService` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (mapKeyValuePairs + sortSearchResults folded, G8a)` |
| `DatabaseFileService` | MERGED | `Merged into Simtabi\Laranail\Toolkit\Services\FileService (validate/validateSize) + ImportDatabaseService (transactional .sql import).` |
| `DatabaseService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\DatabaseService` |
| `ErrorStorageService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\ErrorStorageService` |
| `FakerHelperService` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (faker + randomIntExcept folded, G8a)` |
| `FileHelperService` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (useful static file helpers recovered, restore-candidates)` |
| `FileService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\FileService` |
| `HttpConfigurationService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\HttpConfigurationService` |
| `LivewireComponentService` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireService` |
| `ModelFormatterService` | MERGED | `Simtabi\Laranail\Toolkit\Traits\HasFormatters (working formatters folded, G6d)` |
| `ModelService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\ModelService` |
| `PackageService` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `RouteService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\RouteService` |
| `SessionService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\SessionService` |
| `StringHelperService` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper` |
| `SystemService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\SystemService` |
| `UtilityService` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (arrayToDotNotation/escapeHtml/random/faker folded; sortSearchResults via CollectionMacros, G8a)` |
| `ValidationService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\ValidationService` |

### Simtabi\Laranail\Laravel\Commands

| Legacy type | Status | New target / reason |
|---|---|---|
| `AssetCommand` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CronJobCommand` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `DatabaseCommand` | MERGED | `Merged + hardened into Simtabi\Laranail\Toolkit\Commands\DatabaseManager (laranail::toolkit.database; array-arg Process, Schema-validated truncate).` |
| `InitializeApplication` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LicenseCommand` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `MacrosCommand` | MERGED | `Simtabi\Laranail\Toolkit\Commands\IdeHelperMacros` |
| `MaintenanceCommand` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `SetAppNamespace` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `TidyCommand` | MERGED | `Merged + hardened into Simtabi\Laranail\Toolkit\Commands\Tidy (laranail::toolkit.tidy; storage-confined deletion, gated migrate:fresh).` |

### Simtabi\Laranail\Laravel\Http\Controllers

| Legacy type | Status | New target / reason |
|---|---|---|
| `BaseController` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Controllers\BaseController` |

### Simtabi\Laranail\Laravel\Http\Middleware

| Legacy type | Status | New target / reason |
|---|---|---|
| `ApiMiddleware` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Middleware\ApiMiddleware` |
| `ApiRequestMiddleware` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Middleware\ApiRequestMiddleware` |
| `ApiResponseMiddleware` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Middleware\ApiResponseMiddleware` |
| `EmailObfuscatorMiddleware` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Middleware\EmailObfuscatorMiddleware` |

### Simtabi\Laranail\Laravel\Http\Requests

| Legacy type | Status | New target / reason |
|---|---|---|
| `ApiRequest` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Requests\ApiRequest` |
| `BaseRequest` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Requests\BaseRequest` |

### Simtabi\Laranail\Laravel\Jobs

| Legacy type | Status | New target / reason |
|---|---|---|
| `BaseJob` | MIGRATED | `Simtabi\Laranail\Toolkit\Jobs\BaseJob` |

### Simtabi\Laranail\Laravel\Listeners

| Legacy type | Status | New target / reason |
|---|---|---|
| `BaseListener` | MIGRATED | `Simtabi\Laranail\Toolkit\Listeners\BaseListener` |
| `LicenseListener` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |

### Simtabi\Laranail\Laravel\Macros

| Legacy type | Status | New target / reason |
|---|---|---|
| `After` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `At` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Before` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Bind` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `BrazilianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `CanadianDates` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `CapitalizeWords` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CatchableProxy` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ChunkBy` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `CollectBy` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::collectBy macro)` |
| `Decrement` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `DistanceBetween` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (native Haversine distanceBetween, G8b)` |
| `DutchHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `EachCons` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Eighth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Error` | MERGED | `Simtabi\Laranail\Toolkit\Macros\ResponseMacros (restored as Response::error macro (delegates to ApiResponseTrait))` |
| `Extract` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `FactoryBuilderMixin` | MIGRATED | `Simtabi\Laranail\Toolkit\Macros\FactoryBuilderMixin` |
| `Fifth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FilterMap` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::filterMap macro)` |
| `FirstDifferentLengthAwarePaginator` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FirstOrFail` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FirstOrPush` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `ForSelectBox` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Fourth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FrenchHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `FromBase64` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `FromJson` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (restored as Helper::fromJson)` |
| `FromPairs` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `GenerateName` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (restored as Helper::generateName)` |
| `GermanHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `GetFile` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `GetNth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Glob` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `GroupByModel` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Head` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `HighlightWords` | MERGED | `Simtabi\Laranail\Toolkit\Macros\StringMacros (folded as a Str/Stringable macro, G6a)` |
| `Human` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `IfAny` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::ifAny macro)` |
| `IfEmpty` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `IfMacro` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Increment` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `IndianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `IndonesianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `Initials` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `InsertAfter` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `InsertAfterKey` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::insertAfterKey macro (key-preserving))` |
| `InsertAt` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `InsertBefore` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `InsertBeforeKey` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::insertBeforeKey macro (key-preserving))` |
| `Interpolate` | MERGED | `Simtabi\Laranail\Toolkit\Macros\StringMacros (restored as Str/Stringable interpolate macro (:placeholder))` |
| `IsEquals` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ItalianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `KenyanHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `Krsort` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Ksort` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LinesCount` | MERGED | `Simtabi\Laranail\Toolkit\Macros\StringMacros (restored as Str/Stringable linesCount macro)` |
| `MacroSupport` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Matches` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Message` | MERGED | `Simtabi\Laranail\Toolkit\Macros\ResponseMacros (restored as Response::message macro (delegates to ApiResponseTrait))` |
| `MultiNationalDates` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `Ninth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `None` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::none macro)` |
| `Paginate` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `PaginateFirstDifferent` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `PaginateWithPrevious` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Paginator` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ParallelMap` | DROPPED | `Requires amphp/parallel-functions; native Laravel concurrency supersedes it (G3).` |
| `Path` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Pdf` | MERGED | `Simtabi\Laranail\Toolkit\Macros\ResponseMacros (restored as Response::pdf macro (streams bytes, no unescaped HTML))` |
| `PluckMany` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `PluckToArray` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::pluckToArray macro)` |
| `Prioritize` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ReadingMinutes` | MERGED | `Simtabi\Laranail\Toolkit\Macros\StringMacros (folded as a Str/Stringable macro, G6a)` |
| `Recursive` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `RenameKeys` | MERGED | `Simtabi\Laranail\Toolkit\Macros\ArrMacros (folded as the Arr::renameKeys macro, G6a)` |
| `ReplaceInKeys` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ResponseMacros` | MIGRATED | `Simtabi\Laranail\Toolkit\Macros\ResponseMacros` |
| `Rotate` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Round5` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Rsort` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Second` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `SectionBy` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::sectionBy macro)` |
| `Seventh` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `SimplePaginate` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Sixth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `SliceBefore` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `StripTags` | MERGED | `Simtabi\Laranail\Toolkit\Macros\StringMacros (restored as Str::stripTags macro)` |
| `Success` | MERGED | `Simtabi\Laranail\Toolkit\Macros\ResponseMacros (restored as Response::success macro (delegates to ApiResponseTrait))` |
| `SwedishHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `Tail` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Tenth` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `Third` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ToBase64` | MERGED | `Simtabi\Laranail\Toolkit\Helpers\Helper (restored as Helper::toDataUri)` |
| `ToPairs` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (folded as a registered Collection macro, G6a)` |
| `Transpose` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `TryCatch` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `UkrainianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `UsDates` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |
| `Validate` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `WhenEquals` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `WhereContains` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `WhereEndsWith` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `WhereStartsWith` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `WithSize` | MERGED | `Simtabi\Laranail\Toolkit\Macros\CollectionMacros (restored as Collection::withSize macro)` |
| `WordsCount` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ZambianHolidays` | MERGED | `Macros\CarbonMacros (Carbon holiday macros ported, G3; assignment bugs fixed)` |

### Simtabi\Laranail\Laravel\Observers

| Legacy type | Status | New target / reason |
|---|---|---|
| `BaseObserver` | MIGRATED | `Simtabi\Laranail\Toolkit\Observers\BaseObserver` |

### Simtabi\Laranail\Laravel\Providers

| Legacy type | Status | New target / reason |
|---|---|---|
| `ArchiverServiceProvider` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Archiver\ArchiverServiceProvider` |
| `ArrMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `BladeServiceProvider` | MIGRATED | `Simtabi\Laranail\Toolkit\Providers\BladeServiceProvider` |
| `BlueprintMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CarbonMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `CollectionMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LaravelMiddlewareServiceProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `MacrosServiceProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `QueryBuilderMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `RequestMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ResponseMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `StringMacroProvider` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |

### Simtabi\Laranail\Laravel\Services

| Legacy type | Status | New target / reason |
|---|---|---|
| `ImportDatabaseService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\ImportDatabaseService` |
| `ResponseBuilderService` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `SystemService` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\SystemService` |

### Simtabi\Laranail\Shared\Enums

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarFont` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarFont` |
| `NotificationChannelEnum` | RELOCATED | `laranail/notifications` |

### Simtabi\Laranail\Shared\Events

| Legacy type | Status | New target / reason |
|---|---|---|
| `BaseEvent` | MIGRATED | `Simtabi\Laranail\Toolkit\Events\BaseEvent` |
| `CacheEvents` | MIGRATED | `Simtabi\Laranail\Toolkit\Events\CacheEvents` |
| `EnvironmentEvents` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `LicenseEvents` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `RequirementsEvents` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |

### Simtabi\Laranail\Shared\Exceptions

| Legacy type | Status | New target / reason |
|---|---|---|
| `AuthenticationException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\AuthenticationException` |
| `CollectionItemNotFound` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\CollectionItemNotFound` |
| `ImmutableDataException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\ImmutableDataException` |
| `MissingUuidColumnException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\MissingUuidColumnException` |
| `ModelException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\ModelException` |
| `UuidException` | MIGRATED | `Simtabi\Laranail\Toolkit\Exceptions\UuidException` |

### Simtabi\Laranail\Support\Contracts

| Legacy type | Status | New target / reason |
|---|---|---|
| `CacheHelperInterface` | MERGED | `Merged into Simtabi\Laranail\Toolkit\Utilities\Contracts\CacheRepositoryInterface.` |
| `LoggerServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Utilities\Contracts\LoggerServiceInterface` |
| `ResponseBuilderServiceInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ResponseMacroInterface` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `ShovelHttpInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Http\Contracts\ShovelHttpInterface` |
| `SystemServiceInterface` | MIGRATED | `Simtabi\Laranail\Toolkit\Services\Contracts\SystemServiceInterface` |

### Simtabi\Laranail\Support\Eloquent\Scopes

| Legacy type | Status | New target / reason |
|---|---|---|
| `ArchiveScope` | MIGRATED | `Simtabi\Laranail\Toolkit\Support\Scopes\ArchiveScope` |

### Simtabi\Laranail\Support\Facades

| Legacy type | Status | New target / reason |
|---|---|---|
| `LanguagesFacade` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Atlas\Atlas` |

### Simtabi\Laranail\Support\Resources

| Legacy type | Status | New target / reason |
|---|---|---|
| `AvatarGenerationResource` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarGeneration` |
| `AvatarResolutionResource` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Avatar\AvatarResolution` |
| `GravatarResolutionResource` | MIGRATED | `Simtabi\Laranail\Toolkit\Modules\Gravatar\GravatarResolution` |

### Simtabi\Laranail\Support\Services\Atlas

| Legacy type | Status | New target / reason |
|---|---|---|
| `Countries` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService` |
| `Languages` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Atlas\AtlasService` |

### Simtabi\Laranail\Support\Traits

| Legacy type | Status | New target / reason |
|---|---|---|
| `HasArchiver` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasArchiver` |
| `HasAuth` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasAuth` |
| `HasErrorStorage` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasErrorStorage` |
| `HasGuzzleConfig` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasGuzzleConfig` |
| `HasPackageTools` | DROPPED | `see docs/migration/MIGRATION.md + dropped.md` |
| `RunsConditionally` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\RunsConditionally` |

### Simtabi\Laranail\Support\Traits\Livewire

| Legacy type | Status | New target / reason |
|---|---|---|
| `HasLivewire` | MERGED | `Simtabi\Laranail\Toolkit\Modules\Livewire\HasLivewireComponents` |

### Simtabi\Laranail\Support\Traits\Models

| Legacy type | Status | New target / reason |
|---|---|---|
| `HasAvatar` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasAvatar` |
| `HasFormatters` | MIGRATED | `Simtabi\Laranail\Toolkit\Traits\HasFormatters` |

### Simtabi\Laranail\Support\Utilities

| Legacy type | Status | New target / reason |
|---|---|---|
| `Auth` | MERGED | `Simtabi\Laranail\Toolkit\Utilities\AuthUtil` |
| `BladeDirectives` | MERGED | `Simtabi\Laranail\Toolkit\Providers\BladeServiceProvider` |
| `DatabaseSession` | MIGRATED | `Simtabi\Laranail\Toolkit\Support\Models\DatabaseSession` |
| `Username` | MERGED | `Simtabi\Laranail\Toolkit\Traits\HasFormatters` |

### Simtabi\Laranail\Support\Utilities\Runners

| Legacy type | Status | New target / reason |
|---|---|---|
| `ConditionalRunner` | MIGRATED | `Simtabi\Laranail\Toolkit\Support\ConditionalRunner` |

### Simtabi\Laranail\Support\Utilities\SystemIO

| Legacy type | Status | New target / reason |
|---|---|---|
| `DiskSpaceValidator` | MERGED | `Simtabi\Laranail\Toolkit\Support\Diagnostics\RequirementsDiagnostics` |
| `Environment` | MERGED | `Simtabi\Laranail\Toolkit\Utilities\EnvironmentUtil` |
| `RequirementsChecker` | MERGED | `Simtabi\Laranail\Toolkit\Support\Diagnostics\RequirementsDiagnostics (useful probes folded, G6c)` |

<!-- LEDGER:END -->
