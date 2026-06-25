# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Atlas continent & region API + single config.** New `Atlas::continents()`,
  `countriesByContinent()`, `countriesInContinent()` (by ISO code or English
  name), `continentForCountry()` (ISO2/ISO3), `regions()`, and `subregions()`.
  The Laravel-locale registry moved under `atlas.languages` so Atlas now owns a
  **single, publishable `config/atlas.php`** (publish tag
  `laranail-toolkit-atlas`, merged under `laranail.toolkit.atlas`).
- **Llm module provider + facade.** A dedicated deferred `LlmServiceProvider`
  binds `LLMProviderInterface` (alias `laranail.llm`) and registers a `Llm`
  facade (`Llm::generateResponse(...)`) alongside constructor injection.
- **Security credential generators** — CSPRNG `Support\Security\{Token, Password,
  Passphrase}` (fluent, immutable), backed by `resources/data/security/*` (the
  EFF large wordlist + common-password list). See
  [docs/security.md](docs/security.md).
- **Two more captcha providers** — Friendly Captcha and a no-op Null provider,
  bringing the captcha module to **five** providers (reCAPTCHA, hCaptcha,
  Turnstile, Friendly Captcha, Null) behind one `CaptchaProviderInterface`.
- **Fluent `Support\Username` builder.** A native, immutable, chainable
  username / handle generator (`Username::for()` / `fromEmail()` / `fromName()` /
  `random()`) that replaces the legacy pheg-bound `name2username()`. Supports
  configurable separator / case / length, ASCII transliteration, prefix/suffix,
  reserved-word filtering, leading-letter enforcement, deterministic
  `candidates()`, and a backend-agnostic `unique()` checker backed by a
  **bounded** uniqueness loop (throws instead of recursing forever). The
  `Helper::usernameFromEmail()` / `nameToUsernames()` / `generateUsername()`
  shortcuts and the `HasFormatters::suggestUsername()` trait now delegate to it.
  See [docs/username.md](docs/username.md).
- **Commands now use the full `laranail/console` feature set.** All four Artisan
  commands (`make-crud`, `ide-helper-macros`, `database`, `tidy`) adopt the console
  base's fluent `consoleWriter()` (success/error/warning/info/note statuses) and the
  `$this->services` lifecycle: `performance()` timing, `signals()` graceful-shutdown
  handling (the `database` clean loop and the `tidy` sweep are now **signal-safe**,
  bailing between units of work on SIGTERM/SIGINT; a no-op without ext-pcntl),
  `interaction()->confirmAction()` confirmations (safe default in non-interactive /
  CI runs), `logger()->logCompletion()` summaries, `error()->logError()` failure
  capture that **auto-redacts** `password`/`secret`/`token`/`key`, and per-run
  `metadata()`. Behaviour, signatures and exit codes are unchanged, and the existing
  G10 security hardening (mysqldump array-args + chmod-600 defaults-file, the
  Schema-validated grammar-quoted truncate, the FilePathGuard storage confinement and
  the `db` gating) is preserved.
- **API-surface regression proof** — a `tests/Regression/ApiSurfaceTest` diffs the
  frozen legacy public-API snapshot against the current toolkit and fails on any
  *unplanned* lost symbol; intentional removals/relocations live in
  `tests/Fixtures/Legacy/removed-symbols.json` (kept honest by a no-stale-entries
  check). Plus behavioural parity snapshots (`ParityTest`).
- **Unified `Toolkit` facade** (`Simtabi\Laranail\Toolkit\Facades\Toolkit`, alias
  `Toolkit`) fronting the feature modules — `Toolkit::avatar()/gravatar()/captcha()/
  archiver()` return the module's typed service. The modern, typed replacement for the
  legacy 48-method `Laranail` service-locator (see `docs/migration/MIGRATION.md`).
- **Module facade aliases** registered for `Avatar`, `Gravatar`, `Captcha`, and
  `Archiver`, so `Avatar::…` etc. work out of the box.

### Changed

- **Hardened `RejectCommonPasswords`** (larger curated common-password list,
  case-insensitive matching) and **`Support\Username` now rejects spaces** so
  generated handles are always whitespace-free.
- **Judicious Str/Arr/File helper sweep** across `Helpers\Helper` and its
  `Concerns\InteractsWith*` traits — replacing hand-rolled logic with the
  Laravel 13 / PHP 8.4 natives where they are an exact behavioural match.
- **Restructured to a flat, feature-first `src/`** (behaviour-preserving): each
  `Modules/<Feature>/` now holds its files directly (the `Services/`, `Contracts/`,
  `DataTransferObjects/`, `Facades/`, `Enums/`, `Support/`, `Results/` sub-folders
  were flattened up one level; only multi-file groups like `Captcha/Providers/` keep
  a sub-folder). `src/LLMProviders/` moved to `src/Modules/Llm/` (per-driver folders
  kept). Namespaces follow: e.g. `…\Modules\Avatar\Services\AvatarService` →
  `…\Modules\Avatar\AvatarService`, `…\LLMProviders\Claude\ClaudeProvider` →
  `…\Modules\Llm\Claude\ClaudeProvider`.
- **`laranail/console` bumped to `^2.5.0`** (the release carrying the first-class
  `ConsoleWriter` + the nine command services), and the four commands rewritten to
  consume its full feature set (see _Added_).

### BREAKING

- **The `Utilities\` namespace was removed.** Every `*Util` class was split by
  responsibility into injectable, interface-backed `Services\*` and pure static
  `Support\*`. Update imports as follows:

  | Removed (`Toolkit\Utilities\…`) | New home |
  |---|---|
  | `CachingUtil` | `Services\CacheService` (`Services\Contracts\CacheRepositoryInterface`) |
  | `LoggingUtil` | `Services\LogService` (`Services\Contracts\LoggerServiceInterface`) |
  | `ConfigUtil` | `Support\Config` |
  | `SettingsStore` | `Services\SettingsStore` (`Services\Contracts\SettingsStoreInterface`) |
  | `RateLimiterUtil` | `Services\RateLimiterService` (`Services\Contracts\RateLimiterServiceInterface`) |
  | `SchedulerUtil` | `Services\SchedulerService` (`Services\Contracts\SchedulerServiceInterface`) |
  | `AuthUtil` | `Support\AuthHelper` |
  | `EnvironmentUtil` | `Support\Environment` |
  | `FeatureToggleUtil` | `Support\FeatureToggle` |
  | `FilteringUtil` | `Support\CollectionFilter` |
  | `PaginationUtil` | `Support\Pagination` |
  | `QueryParameterUtil` | `Support\QueryParameters` |

- **Structure moves.** `Support\Scopes\ArchiveScope` → `Scopes\ArchiveScope`;
  `Support\Models\DatabaseSession` → `Models\DatabaseSession`;
  `Support\FilePathGuard` → `Traits\FilePathGuard`;
  `Support\Diagnostics\RequirementsDiagnostics` → `Support\RequirementsDiagnostics`;
  `Observers\BaseObserver` → `Observers\Observer`;
  `Modules\AccessLog\*` → `Modules\Security\AccessLog\*`.
- **PSR / contract renames.** `Llm\LlmRequestException` → `Modules\Llm\LLMRequestException`;
  `Http\Contracts\ShovelHttpInterface` → `Http\Contracts\HttpStatusInterface`;
  `Services\AuthenticationHelperService` → `Services\AuthenticationContextService`
  (+`AuthenticationContextServiceInterface`).
- **The command base now comes from `laranail/console`** — the toolkit no longer
  ships its own `Commands\Command` + `SupportsNamespacedNames` (the unique
  `$commandAliases` convenience was merged upstream into console). `MakeCrud`
  extends `Simtabi\Laranail\Console\Tools\Commands\Command`.
- **PHP floor raised to `^8.4.1`** (drop 8.3) — mandated by the `laranail/console`
  dependency. CI matrix is now `8.4 / 8.5 × Laravel 13`.

### Removed

- **BREAKING:** the **Notifications** module was extracted into the dedicated
  [`laranail/notifications`](https://opensource.simtabi.com/notifications/) package
  (hardened: SSRF-guarded outbound channels, typed message DTO, channel allow-list,
  serializable queue job). The toolkit no longer ships notifications — install
  `laranail/notifications` instead. Removed `src/Modules/Notifications/**`,
  `config/notifications.php`, and the `Notifications` provider/facade.
- The standalone `config/languages.php` was **deleted** — its Laravel-locale map
  now lives under `atlas.languages` in `config/atlas.php`.

> Note: `laranail/console` is pinned to `^2.5.0`. Local development resolves it via
> the `path` repository (`../../tools/console`), whose checkout currently reports
> `2.x-dev` (the path repo carries no version tag); release/CI install the published
> `^2.5.0` tag.

First tagged release. Migrated and hardened from the legacy `LaraUtilX` /
`laranail/laranail` monolith into a single cohesive, security-first toolkit.

### Changed

- **BREAKING (identity):** the package is `laranail/toolkit`
  (`Simtabi\Laranail\Toolkit`), realigned to Simtabi/laranail org conventions.
  It supersedes the legacy `laranail/laranail` monolith, which is being merged in.
- Realigned tooling to PHP `^8.3 || ^8.4 || ^8.5` and Laravel `^13.0`
  (Pest 3, Orchestra Testbench 11, PHPStan 2, Pint).
- Artisan commands now use the org-wide naming shape
  `laranail::toolkit.<command>`. `make:crud` is now
  `laranail::toolkit.make-crud` with a `make:crud` alias retained for ergonomics.

### Fixed

- Service provider config wiring: config is merged under the `laranail.toolkit`
  key from `config/toolkit.php`; corrected publish paths and the cross-platform
  `Models` path; removed the phantom service-provider publish.
- Corrected autoload-fatal namespace imports in the Claude LLM provider and the
  CRUD generator; fixed the published-stub path.
- Removed all leftover `LaraUtilX` / `lara-util-x` rename debris (the package
  was originally `LaraUtilX`).
- Renamed the mistyped `blade-jvascript.blade.php` view to
  `blade-javascript.blade.php`.

### Added

- Self-contained `Core\Console\Command` base + `SupportsNamespacedNames` trait
  enabling the `laranail::toolkit.*` command naming with no `laranail/*`
  runtime dependency.
- **Feature modules** (`src/Modules/*`, each a deferred, DI-driven provider with
  no `laranail/*` dependency):
  - **Gravatar** — immutable fluent URL builder (HTTPS default) + `resolve()` DTO.
  - **Avatar** — initials-image generation (Intervention Image v4, GD/Imagick) +
    a `string|Model|callable` resolution engine with caching/fallback; native
    `AvatarFont` enum.
  - **Captcha** — reCAPTCHA/Turnstile/hCaptcha drivers; **fails closed** on
    transport errors; provider allow-list.
  - **Notifications** — 12 channels, typed message DTO, **SSRF-guarded** outbound
    channels, serializable queue job, native console channel.
  - **Archiver** — zip/tar/tar.gz extraction, **Zip-Slip hardened** (validate
    entries before extraction; symlink + zip-bomb guards).
- **LLM providers** — OpenAI/Claude/Gemini behind `LLMProviderInterface`
  (provider selected via `config('laranail.toolkit.llm.default_provider')`),
  with retryable-only retries and an SSRF base-URL guard.
- **Macros** — consolidated grouped providers (String/Collection/Arr/
  QueryBuilder/Blueprint/Request) + `FactoryBuilderMixin`.
- **Support** — `ArchiveScope`, `HasArchiver`/`HasAvatar`/`HasFormatters` traits,
  `FilePathGuard`, `RequirementsDiagnostics` (surfaced via `php artisan about`),
  and custom-only Blade directives.
- `SECURITY.md`, `CHANGELOG.md`, `.editorconfig`, `.github/` issue & PR templates,
  Dependabot, and a corrected CI matrix (PHP 8.3/8.4/8.5 × Laravel 13).
- Larastan added to the static-analysis gate.

### Security

- AccessLog middleware redacts sensitive request keys, drops query-string secrets,
  and logs after the response (fail-safe); `AccessLog` uses explicit `$fillable`.
- `CrudController` and the `make:crud`-generated controller: no arbitrary mass
  assignment, escaped LIKE wildcards, whitelisted sorting, clamped `per_page`,
  correct unique-ignore on update.
- `Auditable` redacts a model's hidden attributes and never breaks the write.
- Gemini API key sent via header (not the URL query).

### Removed

- Dropped legacy code that duplicates Laravel 13 / PHP 8.3–8.5 natives — see
  `docs/migration/dropped.md` for the cited removal record (Conditionable,
  `disk_free_space`, `Log`+`Context`, native Blade directives, native auth/session,
  `rinvex/countries` for geo data, etc.).
