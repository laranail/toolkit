# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- `Passphrase::capitalize('title')` now title-cases each segment of a hyphenated
  compound word (`felt-tip` → `Felt-Tip`) rather than only the first segment,
  matching the documented "title-case every word" contract and removing an
  intermittent test failure when a hyphenated EFF word was drawn.

### Tests

- Raised the mutation score (Infection) on the in-scope code (`src/Helpers`,
  `src/Macros`, `src/Support`) past the committed gate (`minMsi 80` /
  `minCoveredMsi 85`) — Covered Code MSI ~77% → ~88%. Added exhaustive
  exact-value behaviour tests for the Arr/String/Collection/Carbon macros and the
  `Username` support helper (no source or threshold changes).
- Package-wide coverage pass — line coverage ~93.6% → ~97.5% (suite 1225 → 1373
  tests). Added edge-case/unit/regression tests for the Observer base, model &
  controller traits, the Archiver, Captcha, Avatar and LLM modules, the HTTP
  layer (middleware/requests/CRUD controller/payload-key mutation), security
  data loading, the validation/model services and `ToolkitManager`. Passphrase
  tests are now deterministic (reflection-pinned wordlist) instead of relying on
  random draws.

## [0.1.0] - 2026-06-26

### Added

- **String-similarity macros** on `Str`/`Stringable` — `levenshtein`,
  `similarText` (percentage), `jaroWinkler` (pure-PHP), and `closest`. Native, no
  third-party dependency; restores the legacy `laravel-string-similarities` doc.
- **Carbon macros documentation** (`docs/carbon-macros.md`) — the ~90
  national-calendar predicates (15 countries) + date helpers in `CarbonMacros`
  were previously undocumented. Expanded the Avatar module docs (font enum,
  resolution fallback order, field mappings, callback context) and recorded the
  disposition of every legacy `docs/` topic in the migration ledger.
- **Documentation completeness pass.** Every shipped public class now has a doc
  page or section: `docs/traits.md` gains `HasAuth`, `HasErrorStorage`,
  `HasGuzzleConfig`, `RunsConditionally`, and a standalone `FilePathGuard`;
  `docs/utilities.md` gains the `AuthenticationContextService`,
  `ErrorStorageService`, `HttpConfigurationService`,
  `RouteService`, and `ValidationService` services plus the `Cast`, `Config`,
  `ConditionalRunner`, and `ApiResponder` Support helpers. The LLM provider docs
  moved to `docs/modules/llm.md` for module-pattern consistency.
- **Tests** for the previously-untested `Support\Cast`, `Support\Config`,
  `Support\ApiResponder`, and the `HasGuzzleConfig` trait (4 new suites).
- **Atlas continent & region API + single config.** New `Atlas::continents()`,
  `countriesByContinent()`, `countriesInContinent()` (by ISO code or English
  name), `continentForCountry()` (ISO2/ISO3), `regions()`, and `subregions()`.
  The Laravel-locale registry moved under `atlas.languages` so Atlas now owns a
  **single `config/atlas.php`**, merged under `laranail.toolkit.atlas` and
  published with the other configs via `laranail::toolkit-config`.
- **LLM module provider + facade.** A dedicated deferred `LLMServiceProvider`
  binds `LLMProviderInterface` (alias `laranail.llm`) and registers a `LLM`
  facade (`LLM::generateResponse(...)`) alongside constructor injection.
- **Security credential generators** — CSPRNG `Modules\Security\{Token, Password,
  Passphrase}` (fluent, immutable), backed by the merged `config/security.php`
  (the EFF large wordlist + common-password list) via the lazy
  `Modules\Security\SecurityData` accessor. See
  [docs/security.md](docs/security.md).
- **zxcvbn password-strength support** (via `bjeavons/zxcvbn-php ^1.4`). `Password`
  gains `minStrength(int $score)` (regenerate until the zxcvbn 0–4 score is met),
  a static `Password::strength()` estimator, and `zxcvbn_*` keys in
  `generateWithMetadata()`; `RejectCommonPasswords` gains an opt-in
  `minZxcvbnScore` gate (constructor + builder). All zxcvbn calls are guarded by
  `class_exists(\ZxcvbnPhp\Zxcvbn::class)`, so the feature degrades gracefully if
  the dependency is removed. See [docs/security.md](docs/security.md).
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
- **Commands now use the full `laranail/console` feature set.** All Artisan
  commands (`make-crud`, `ide-helper-macros`, `tidy`) adopt the console base's
  fluent `consoleWriter()` (success/error/warning/info/note statuses) and the
  `$this->services` lifecycle: `performance()` timing, `signals()` graceful-shutdown
  handling (the `tidy` sweep is now **signal-safe**, bailing between units of work
  on SIGTERM/SIGINT; a no-op without ext-pcntl),
  `interaction()->confirmAction()` confirmations (safe default in non-interactive /
  CI runs), `logger()->logCompletion()` summaries, `error()->logError()` failure
  capture that **auto-redacts** `password`/`secret`/`token`/`key`, and per-run
  `metadata()`. Behaviour, signatures and exit codes are unchanged, and the existing
  security hardening (the FilePathGuard storage confinement) is preserved.
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

- **Merged security data into a single `config/security.php`** behind the lazy
  `Modules\Security\SecurityData` accessor (`commonPasswords()`,
  `passphraseWords()`, `redactKeys()`). The two `resources/data/security/*`
  files (common-password denylist + EFF wordlist, 560 / 7776 entries verbatim)
  are removed in favour of the `passwords.common`, `passphrases.wordlist`, and
  `redact_keys` sections, merged under `laranail.toolkit.security`. `SecurityData`
  reads `config('laranail.toolkit.security.*')` (with a `__DIR__` fallback when no
  app is booted); `RejectCommonPasswords`, `Passphrase`, and `AccessLogMiddleware`
  now read through it (behaviour unchanged).
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
  a sub-folder). `src/LLMProviders/` moved to `src/Modules/LLM/` (per-driver folders
  kept). Namespaces follow: e.g. `…\Modules\Avatar\Services\AvatarService` →
  `…\Modules\Avatar\AvatarService`, `…\LLMProviders\Claude\ClaudeProvider` →
  `…\Modules\LLM\Claude\ClaudeProvider`.
- **`Modules\Llm` renamed to `Modules\LLM`** (and `LlmServiceProvider` →
  `LLMServiceProvider`), so the namespace casing matches the `LLM` facade and the
  `LLMProviderInterface` contract.
- **Events + Listeners folded into the `Modules\Eventing` module.** The top-level
  `Events\` / `Listeners\` moved to `Modules\Eventing\Events\` /
  `Modules\Eventing\Listeners\`, and the abstract event base was renamed
  `Events` → `Event` (now `Modules\Eventing\Events\Event`; `CacheEvents extends
  Event`). The listener base is `Modules\Eventing\Listeners\Listener`. See
  [docs/base-classes.md](docs/base-classes.md).
- **`laranail/console` bumped to `^2.5.0`** (the release carrying the first-class
  `ConsoleWriter` + the nine command services), and the commands rewritten to
  consume its full feature set (see _Added_).

### BREAKING

- **BREAKING: relocated all UUID & database tooling to `laranail/database-tools`.**
  The toolkit no longer ships UUID or low-level database features — they now live
  in the sibling
  [`laranail/database-tools`](https://opensource.simtabi.com/database-tools/)
  package. Moved out: `Traits\HasUuid`; `Exceptions\UuidException` /
  `MissingUuidColumnException`; `Services\DatabaseService` and
  `Services\ImportDatabaseService` (+ their contracts); the
  `laranail::toolkit.database` (`laranail:database`) Artisan command
  (`Commands\DatabaseManager`); the `Macros\BlueprintMacros` schema macros
  (`addCommonFields` / `addUserFields` / `addUuidPrimaryKey` / `addNullableMorphs` /
  `addSlugField` / …); `Support\Pagination`; `Traits\HasArchiver` +
  `Modules\Model\Scopes\ArchiveScope` (soft-archive `archived_at`);
  `Modules\Security\Session\DatabaseSession`; `Helper::uuid()`; and the
  `using_uuids_for_id` / `using_ulids_for_id` / `type_id` config keys.
  **Migrate:** `composer require laranail/database-tools` and use its
  `Concerns\HasUuid` / `Services\DatabaseService` / `Schema\BlueprintMacros` and
  the `laranail::database-tools.db` command.
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
  | `QueryParameterUtil` | `Support\QueryParameters` |

- **Structure moves.** `Support\FilePathGuard` → `Traits\FilePathGuard`;
  `Support\Diagnostics\RequirementsDiagnostics` → `Support\RequirementsDiagnostics`;
  `Observers\BaseObserver` → `Observers\Observer`;
  `Modules\AccessLog\*` → `Modules\Security\AccessLog\*`;
  the top-level `Events\` / `Listeners\` (base `Events` / `Listener`) moved into the
  `Modules\Eventing` module — `Modules\Eventing\Events\Event` (base renamed
  `Events` → `Event`) and `Modules\Eventing\Listeners\Listener`.
- **PSR / contract renames.** `Llm\LlmRequestException` → `Modules\LLM\LLMRequestException`;
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
- **`ToolkitServiceProvider` is fully declarative on `laranail/package-tools`'
  (`^1.2`) `configurePackage()` API** — `hasConfigFile([…])`, `hasViews`,
  `hasTranslations`, `discoversMigrations`/`runsMigrations`, `hasCommands`,
  `registerMiddlewareAliases([…])`, `hasChildProviders`, `hasValidationRules([…])`,
  `hasAboutSections([…])`, and `publishFile()`/`publishDirectory()` (namespaced
  `::` tags) for the security file + CRUD stubs; bindings live in
  `packageRegistered()`. The provider uses the array/batch fluent forms throughout
  rather than repeated single calls.
- **Translations now load from `resources/lang/`** (package-tools' standard path)
  under the `laranail/toolkit` namespace — previously they sat unloaded in
  `resources/assets/lang/`. (Namespace changed from `laranail-toolkit`; the bundled
  translations are not referenced by the package itself, so impact is nil.)
- Removed two dead config files (`config/archiver.php`, `config/config.php`) that
  were never merged or read.
- **Security datasets are now a merged config.** `config/security.php` is merged
  under `laranail.toolkit.security` (with the other configs, published via
  `laranail::toolkit-config`); `SecurityData` reads
  `config('laranail.toolkit.security.*')` when an app is booted, falling back to a
  `__DIR__`-relative read of the package default when none is (framework-free). The
  separate `laranail::toolkit-security` publish tag is gone.
- Standardised on the explicit `(new X())->y()` form over the parens-less PHP 8.4
  `new X()->y()` syntax across the package (Rector's
  `NewMethodCallWithoutParenthesesRector` is skipped to preserve it).
- **Config is now the standard namespaced `config('laranail.toolkit.*')`** (dotted,
  collision-free): the main config plus `feature-toggles`, `atlas` and `captcha`
  each merge at their own sub-key and publish under the **`laranail::toolkit-config`**
  tag; editing a published file overrides the dotted value (via package-tools'
  publish-override bridge). The former flat `laranail-toolkit` key + manual alias
  are gone.
- **Publish tags now use the `laranail::toolkit-*` convention**
  (`-config`, `-views`, `-translations`, `-migrations`, `-stubs`). Security datasets
  are part of `-config` (merged under `laranail.toolkit.security`). The utility
  services/helpers, the `reject_common_passwords` rule, `ApiResponseTrait` and the
  `AccessLog` model are **no longer publishable** — they are used directly from the
  package.
- Realigned tooling to PHP `^8.4.1 || ^8.5` and Laravel `^13.0`
  (Pest, Orchestra Testbench, PHPStan 2, Pint).
- Artisan commands now use the org-wide naming shape
  `laranail::toolkit.<command>`. `make:crud` is now
  `laranail::toolkit.make-crud` with a `make:crud` alias retained for ergonomics.

### Fixed

- Service provider config wiring: configs are merged under the dotted
  `laranail.toolkit.*` namespace from `config/toolkit.php` (with `feature-toggles`,
  `atlas`, `captcha`, and `security` each at their own sub-key); corrected publish
  paths and the cross-platform `Models` path; removed the phantom service-provider
  publish. Published overrides take effect via package-tools' config bridge.
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
