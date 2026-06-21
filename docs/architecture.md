# Architecture

The toolkit favours small, contract-bound pieces over a single god-class. The
root `ToolkitServiceProvider` wires everything; feature modules are isolated and
deferred so they can later be extracted into stand-alone packages.

## Source layout

```
src/
├── Providers/ToolkitServiceProvider.php   # the one entry point
├── Commands/MakeCrud.php                   # make-crud generator
├── Core/Console/                           # base Command + namespaced-name concern
├── Http/
│   ├── Controllers/CrudController.php       # abstract base controller
│   └── Middleware/AccessLogMiddleware.php   # access.log
├── Models/AccessLog.php
├── Enums/LogLevel.php
├── LLMProviders/                           # OpenAI / Claude / Gemini + contract
├── Laravel/
│   ├── Macros/                             # grouped macro providers
│   └── Blade/BladeServiceProvider.php       # custom-only directives
├── Modules/                                # self-contained feature modules
│   ├── Avatar/  Gravatar/  Captcha/  Notifications/  Archiver/
├── Support/
│   ├── FilePathGuard.php                    # `..` / null-byte guard
│   ├── Scopes/ArchiveScope.php              # soft-archive global scope
│   └── Diagnostics/RequirementsDiagnostics.php
├── Traits/                                  # ApiResponse, Auditable, ...
├── Utilities/                              # 9 utility classes
├── Rules/RejectCommonPasswords.php
└── Helpers/XHelper.php
```

## Deferred feature modules

Each module under `src/Modules/` is a self-contained unit with its own
`Contracts/`, `Services/`, `Facades/`, optional DTOs/enums, and a dedicated
`ServiceProvider`. The root provider registers all five module providers:

```php
GravatarServiceProvider, AvatarServiceProvider, CaptchaServiceProvider,
NotificationServiceProvider, ArchiverServiceProvider
```

Every module provider:

- **is deferred** (`DeferrableProvider`) — its services are only booted when
  first resolved, keeping the framework boot lean;
- **binds by interface** (e.g. `AvatarServiceInterface` → `AvatarService`);
- **registers a string alias** (`laranail.<module>`) and a Facade.

| Module | Contract | Alias | Facade |
|--------|----------|-------|--------|
| Gravatar | `GravatarServiceInterface` | `laranail.gravatar` | `Gravatar` |
| Avatar | `AvatarServiceInterface` | `laranail.avatar` | `Avatar` |
| Captcha | `CaptchaProviderInterface` / `CaptchaService` | `laranail.captcha` | `Captcha` |
| Notifications | `NotificationService` | `laranail.notifications` | `Notifications` |
| Archiver | `ArchiverServiceInterface` | `laranail.archiver` | `Archiver` |

Resolve any module by its contract (preferred, for testability) or its facade.

## Eager vs. deferred

Two things must register eagerly and so run in `boot()`, not deferred:

- **Macros** — `MacroServiceProvider` registers all Str/Arr/Collection/Query/
  Request/Blueprint/Factory macros globally.
- **Blade directives** — `BladeServiceProvider` registers custom directives.

The middleware alias `access.log`, the `reject_common_passwords` validator, and
the `php artisan about` diagnostics are also wired in `boot()`.

## LLM provider selection

`LLMProviderInterface` is bound to a single driver chosen at resolution time
from `config('laranail.toolkit.llm.default_provider')` — `openai` (default),
`claude`, or `gemini`. See [LLM providers](llm-providers.md).

## Migration / removal record

This package was ported from the pre-1.0 `Simtabi\Laranail` monolith, keeping
only the genuine delta over Laravel 13 / PHP 8.3–8.5 natives. The full list of
dropped legacy code (each with its native replacement) and the kept items lives
in [migration/dropped.md](migration/dropped.md).

[← Docs index](../README.md#documentation)
