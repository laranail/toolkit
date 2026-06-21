# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **BREAKING:** the command base now comes from **`laranail/console`** — the toolkit
  no longer ships its own `Commands\Command` + `SupportsNamespacedNames` (the unique
  `$commandAliases` convenience was merged upstream into console). `MakeCrud` extends
  `Simtabi\Laranail\Console\Tools\Commands\Command`.
- **BREAKING:** PHP floor raised to **`^8.4.1`** (drop 8.3) — mandated by the
  `laranail/console` dependency. CI matrix is now `8.4 / 8.5 × Laravel 13`.

> Note: until `laranail/console` cuts a release carrying the `$commandAliases`
> convenience (≥ v1.3.0), local development resolves console via a `path`
> repository (`../../tools/console`); release/CI will pin `laranail/console: ^1.3`.

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
