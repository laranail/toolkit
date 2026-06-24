# Architecture

The toolkit favours small, contract-bound pieces over a single god-class. The
root `ToolkitServiceProvider` wires everything; feature modules are isolated and
deferred so they can later be extracted into stand-alone packages (as the
notifications module already was — it now lives in `laranail/notifications`).

## Source layout

The layout is **flat and feature-first**: cross-cutting glue sits at the top of
`src/`, and every feature is a single folder under `src/Modules/` with its files
directly inside it (a sub-folder only for a genuinely multi-file group such as
`Captcha/Providers/` or the per-driver LLM folders).

```
src/
├── Providers/
│   ├── ToolkitServiceProvider.php          # the one entry point
│   └── BladeServiceProvider.php            # custom-only Blade directives
├── Facades/Toolkit.php                     # unified entry facade
├── ToolkitManager.php                      # Toolkit::avatar()/gravatar()/...
├── Commands/MakeCrud.php                   # make-crud (extends the laranail/console base)
├── Http/Controllers/CrudController.php     # abstract base controller
├── Services/                               # injectable, interface-backed services (File, System, Database, ...)
├── Macros/                                 # grouped macro providers + MacroServiceProvider
├── Traits/                                 # ApiResponse, Auditable, HasAvatar, ...
├── Utilities/                              # 9 utility classes (Caching/Logging now interface-backed)
├── Support/                                # FilePathGuard, Scopes/ArchiveScope, Diagnostics/
├── Enums/LogLevel.php
├── Rules/RejectCommonPasswords.php
├── Helpers/Helper.php                       # static PURE-function facade (arrays/strings/dates/geo/console); Concerns/InteractsWith* traits
                                             #   (file/system/database domains moved to injectable Services/*)
└── Modules/                                # self-contained feature modules (flat inside)
    ├── Avatar/        AvatarService, AvatarServiceInterface, AvatarFont, DTOs, Avatar facade, provider
    ├── Gravatar/      GravatarService, …, Gravatar facade, provider
    ├── Captcha/       CaptchaService, …, Providers/{Recaptcha,Hcaptcha,Turnstile}, Captcha facade, provider
    ├── Archiver/      ArchiverService, ArchiveManager, Zip/Tar/TarGz/Extractor, Archiver facade, provider
    ├── AccessLog/     AccessLogMiddleware, AccessLog (model)
    └── Llm/           LLMProviderInterface, Claude/, Gemini/, OpenAI/, RetriesHttpRequests
```

> The command base (`Command` + `SupportsNamespacedNames`) is **not** local — it
> comes from [`laranail/console`](https://opensource.simtabi.com/console/), the
> org-canonical command base. `MakeCrud` extends it.

## Adding a feature / tool / module

The layout is designed so a new feature is a drop-in folder:

1. Create `src/Modules/<Name>/` with `<Name>Service.php`, `<Name>ServiceInterface.php`,
   a `<Name>Facade.php`, and a deferred `<Name>ServiceProvider.php` (plus DTOs/enums
   as needed). Keep a sub-folder only for a genuinely multi-file group.
2. Register the provider in `ToolkitServiceProvider::MODULE_PROVIDERS`.
3. (Optional) add a `Toolkit::<name>()` accessor on `ToolkitManager` and a Laravel
   alias in `composer.json` `extra.laravel.aliases`.

## Deferred feature modules

Each module provider:

- **is deferred** (`DeferrableProvider`) — services boot only when first resolved,
  keeping the framework boot lean;
- **binds by interface** (e.g. `AvatarServiceInterface` → `AvatarService`);
- **registers a string alias** (`laranail.<module>`) and a Facade.

| Module | Contract | Alias | Facade |
|--------|----------|-------|--------|
| Gravatar | `GravatarServiceInterface` | `laranail.gravatar` | `Gravatar` |
| Avatar | `AvatarServiceInterface` | `laranail.avatar` | `Avatar` |
| Captcha | `CaptchaProviderInterface` / `CaptchaService` | `laranail.captcha` | `Captcha` |
| Archiver | `ArchiverServiceInterface` | `laranail.archiver` | `Archiver` |

Resolve any module by its contract (preferred, for testability), its own facade,
or the unified `Toolkit` facade (`Toolkit::avatar()`, …).

## Eager vs. deferred

Two things must register eagerly and so run in `boot()`, not deferred:

- **Macros** — `MacroServiceProvider` registers all Str/Arr/Collection/Query/
  Request/Blueprint/Factory macros globally.
- **Blade directives** — `BladeServiceProvider` registers custom directives.

The middleware alias `access.log`, the `reject_common_passwords` validator, and
the `php artisan about` diagnostics are also wired in `boot()`.

## Exceptions

All toolkit exceptions extend `Exceptions\LaranailException` — a structured base
carrying context, metadata, a user-facing message and an optional HTTP status.
`Exceptions\AuthenticationException` adds auth factories (`missingGuard()`,
`invalidGuard()`, `unauthenticated()`) and a `render()` method that emits a JSON
`401` envelope for JSON requests (Laravel 11+ calls `render()` directly on the
exception, so no `Handler` subclass is needed) while deferring to the default
rendering for web requests.

Because L11+ apps own exception configuration in `bootstrap/app.php`, the toolkit
does **not** ship a competing handler. Instead the
`Exceptions\Concerns\RendersApiExceptions` trait exposes the reusable logic
(throttled Slack alerts + a JSON `405` renderer) as opt-in registrars:

```php
use Illuminate\Foundation\Configuration\Exceptions;
use Simtabi\Laranail\Toolkit\Exceptions\Concerns\RendersApiExceptions;

->withExceptions(function (Exceptions $exceptions): void {
    RendersApiExceptions::register($exceptions);
    // or just one: ::registerSlackReporter(...) / ::registerMethodNotAllowedRenderer(...)
})
```

## LLM provider selection

`Modules\Llm\LLMProviderInterface` is bound to a single driver chosen at
resolution time from `config('laranail.toolkit.llm.default_provider')` — `openai`
(default), `claude`, or `gemini`. See [LLM providers](llm-providers.md).

## Migration / removal record

This package was ported from the pre-1.0 `Simtabi\Laranail` monolith, keeping
only the genuine delta over Laravel 13 / PHP 8.3–8.5 natives. The complete
per-symbol accounting (migrated / relocated / dropped) is in
[migration/MIGRATION.md](migration/MIGRATION.md); the cited drop rationale is in
[migration/dropped.md](migration/dropped.md). A regression test
(`tests/Regression/ApiSurfaceTest`) enforces that nothing is lost unplanned.

[← Docs index](../README.md#documentation)
