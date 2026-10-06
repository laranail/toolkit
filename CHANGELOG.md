# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] - 2026-10-06

### Removed

- **Breaking: the `laranail::toolkit.tidy` command** (`Simtabi\Laranail\Toolkit\Commands\Tidy`) moved to `laranail/artisan-ui` as `laranail::artisan-ui.tidy`, with the same actions, options and safety rules. Install that package and call the new name; there is no forwarder here.
- **`routes/web.php` and `routes/api.php`.** No provider ever loaded them. `web.php` held seven unauthenticated `GET` closures that rebuilt and cleared caches (`/tidy/*`), and `api.php` was empty. Their actions are in `laranail/artisan-ui`'s authenticated Caches and Tidy quick-action groups.

### Changed

- The branch alias is `0.3.x-dev`. Consumers on `^0.2` stay on 0.2.x and keep `laranail::toolkit.tidy`; require `^0.3` to move.

## [0.2.3] - 2026-10-05

### Added

- `.github/workflows/release.yml`: a pushed `vX.Y.Z` tag now publishes the GitHub Release with that version's CHANGELOG section as its body and a CycloneDX SBOM attached, after checking the branch-alias line and that `[Unreleased]` is empty. `docs/release.md` already described this workflow, but it did not exist, so releases were made by hand and `v0.2.1` never got one. It can also be run by hand for an existing tag.

### Changed

- `docs/release.md` describes the release-branch process (`release/vX.Y.Z`, merged by pull request, tag on the merge commit). It used to say to push to `main` and that CI runs on every push; CI runs on pull requests.

## [0.2.2] - 2026-10-05

### Changed

- `laravel/framework ^13.0` is now declared in `require`. `src/` uses `Application`, `AuthorizesRequests`, `Dispatchable`, `Exceptions` and others from `Illuminate\Foundation`, which no `illuminate/*` component ships, so the dependency only arrived through the host application.

## [0.2.1] - 2026-10-05

### Added

- **Container keys `laranail.toolkit.access-log` and `laranail.toolkit.helper`**, also exposed as
  `ToolkitServiceProvider::ACCESS_LOG` and `::HELPER`. They keep the lifetimes of the keys they
  replace: a fresh `AccessLog` per resolve, one shared `Helper`.
- **Views and translations also answer to the canonical `laranail/toolkit::` namespace**, over the
  same files and published overrides as `laranail-toolkit::`, which keeps working unchanged.
- **`NamingConventionTest`** asserts the view, translation, middleware and container registries of
  the booted application through package-tools' `AssertsRegisteredNames`. The dev requirement on
  `laranail/package-tools` is raised to `^0.1.3`, the first release that ships it.

### Changed

- **The `dev-main` branch alias is now `0.2.x-dev`** (was `0.1.x-dev`), matching the 0.2 line
  released as `v0.2.0`, so a `dev-main` or path checkout satisfies `^0.2`. The family consumers
  (`crm-tools-vtiger-client`, `sis-wrapper`) move to `^0.2` alongside this.

### Deprecated

- **The bare container keys `AccessLog` and `helper`.** Both sit in the container's flat key map,
  where a host or another package binding the same word silently replaces them. They still resolve
  the same objects through the scoped keys, and raise one `E_USER_DEPRECATED` notice each per
  process naming the replacement. Removal no earlier than the next minor after 0.2.

## [0.2.0] - 2026-10-02

Contains breaking changes: three collection macros are renamed (see **Changed**). Under 0.x semantic versioning a breaking change is a minor bump.

### Changed

- **BREAKING: the `chunkBy` collection macro is now `laranailChunkBy`.** Laravel 13.30.1 added a
  native `Collection::chunkBy()`, and a macro **never runs when a real method of that name
  exists** — `__call` is only reached for missing methods. So the bare name silently resolved to
  Laravel's implementation, which delegates to `chunkWhile()` and **preserves keys**, while this
  macro re-indexes them.

  | | |
  |---|---|
  | Was | `collect($x)->chunkBy($cb, $preserveKeys)` |
  | Now | `collect($x)->laranailChunkBy($cb, $preserveKeys)` |

  This is the hazard the vendor-scoping convention exists for: a macro name is a flat global
  registry, and a bare one can be taken out from under you — here by the framework itself. Callers
  wanting Laravel's semantics should use `chunkBy`, which now reaches the native method; callers
  wanting re-indexed chunks or `$preserveKeys` need the new name.

  Two tests caught this. Nothing else would have: the call kept working and quietly returned
  differently-keyed data.

- **Breaking. `Collection::firstOrFail()` and `Collection::before()` are now `laranailFirstOrFail()`
  and `laranailBefore()`** — the same shadowing as `chunkBy` above, found by the same rule. Laravel
  ships both names natively, so neither macro had been firing.

  `firstOrFail` is the one that mattered: upstream's signature is `($key, $operator, $value)` against
  this macro's `($callback, $default)`, and it throws `ItemNotFoundException` rather than the macro's
  message — so a caller passing a callback and a default silently got a different method, and the
  ide-helper was advertising a signature for something that never ran.

  `before` is renamed rather than deleted. It looks equivalent to upstream's by inspection, but that
  was not measurable here, and quietly moving callers onto a different implementation is the failure
  this convention exists to prevent.

  | | |
  |---|---|
  | Was | `collect($x)->firstOrFail($cb, $default)` · `collect($x)->before($v, $strict)` |
  | Now | `collect($x)->laranailFirstOrFail($cb, $default)` · `collect($x)->laranailBefore($v, $strict)` |

  Callers who want upstream's behaviour keep the bare names, which now unambiguously reach Laravel.

### Fixed

- **The zip bomb guard no longer depends on which PHP patch release is installed.** The guard sums
  the uncompressed sizes an archive declares, which is only sound if no entry can produce more than
  it declared. That used to rest on libzip truncating an understated entry and on `extractTo()`
  reporting the truncation as success. PHP 8.4.26 and 8.5.11 (php-src GH-23240) made the same
  truncation fail its CRC: `extractTo()` now raised an `ErrorException` rather than the guard's
  `ArchiveException`, and left the truncated file on disk. Extraction now streams each entry
  through `BoundedEntryWriter`, which reads at most the declared size, verifies the declared
  CRC-32 itself, and renames a verified copy into place. An understated, overstated or corrupt
  entry is refused with `ArchiveException::corruptEntry()` on every runtime, and the files and
  directories the call created are removed.

- **`make:crud --per-page=` generated a controller that paginates by zero.** The default was applied
  with `??`, which substitutes for `null` — what an ABSENT option gives, and an absent `--per-page`
  already arrives as the signature's own `15`. The case `??` does not cover is an option written
  without a value, which arrives as `''` and casts to `0`. That `0` was then interpolated into the
  generated controller as `paginate(0)`, so the mistake shipped in scaffolded code rather than
  failing in the command — which is why no test here caught it. A non-numeric `--per-page=abc` did
  the same.

  Fixed inline rather than by adopting `laranail/package-tools`' `ReadsOptions`: one call site
  removes no code and would add the package-author toolchain to a package that requires only
  `laranail/console`.

## [0.1.0] - 2026-08-15

Initial public release. Folded in during the pre-stable phase:

### Changed

- **Route-middleware aliases are vendor-scoped.** The router's alias map is flat, so a second package
  registering `api.request` does not conflict — it silently replaces this one, and the damage
  surfaces as the wrong middleware running on a route nobody touched. `access.log` and
  `email.obfuscate` are names an application would plausibly pick for itself.

  | Was | Now |
  |---|---|
  | `access.log` | `laranail-toolkit.access-log` |
  | `api.request` | `laranail-toolkit.api-request` |
  | `api.response` | `laranail-toolkit.api-response` |
  | `email.obfuscate` | `laranail-toolkit.email-obfuscate` |

  A dot after the prefix rather than `::`, and that is forced rather than stylistic: Laravel resolves
  an alias with `explode(':', $name, 2)` so `throttle:60,1` can carry parameters, so
  `laranail::toolkit.api-response` would resolve as the middleware `laranail` with the parameter
  `:toolkit.api-response`. The dot form keeps `->middleware('laranail-toolkit.api-response:meta,data')`
  working.

### Fixed

- **The translation namespace was `laranail/toolkit`, with a slash.** That is a namespace, not a
  path: Laravel publishes to `lang/vendor/{namespace}`, so the files landed one directory deeper
  than the loader looks for them — every published translation override was silently ignored while
  the packaged default kept answering. It is `laranail-toolkit` now, matching the view namespace,
  and `vendor:publish --tag=laranail::toolkit-translations` writes to `lang/vendor/laranail-toolkit`.

- **Documentation that still described the removed captcha module.**
  `docs/modules/captcha.md` was still shipping in full, and `architecture.md`,
  `configuration.md`, `installation.md` and `getting-started.md` all still
  referenced the module, its config file and `Toolkit::captcha()`.
  `architecture.md` also told you to register a child provider through
  `configurePackage()->hasChildProviders([...])`, a method that does not exist —
  it is the `CHILD_PROVIDERS` constant on `ToolkitServiceProvider`.

- **`Toolkit::userOrFail()`** throws Laravel's `Illuminate\Auth\AuthenticationException`,
  so an unauthenticated **web** request gets the framework's login redirect (and
  a JSON/API request a `401`) — matching the `auth` middleware — instead of a
  `500`.
- **`Toolkit::userAs()`** carries its generic through the facade `@method`, so
  `Toolkit::userAs(User::class)` is inferred as `?User` (not `?Authenticatable`).
- **`RequirementsDiagnostics` disk-space tests** are environment-robust — they
  pin the thresholds so they no longer fail on a low-free-space runner.
- Corrected the `AuthHelper::userExists()` doc (it is gated to stateful/session
  guards) and the `auth.user_model` config comment (a reserved hint, not read at
  runtime — the `userAs()` generic provides the IDE typing).

### Removed

- **`Modules\Atlas`, `Modules\Avatar` and `Modules\Gravatar`** — extracted to
  [`laranail/atlas`](https://opensource.simtabi.com/documentation/laranail/atlas/) and
  [`laranail/avatar`](https://opensource.simtabi.com/documentation/laranail/avatar/). See
  [UPGRADING.md](UPGRADING.md); every removed FQCN is recorded in
  `tests/Fixtures/Legacy/removed-symbols.json`.

  **3,674 lines of `src/` and two dependencies leave with them** — `rinvex/countries` (~17 MB) and
  `intervention/image` had exactly one consumer each.

  Three bundled fonts go too, and two of them for licensing reasons rather than size:
  `FreeSerif.ttf` is GPL-3.0, whose font exception covers documents that *embed* the font rather
  than redistribution of the file, and `msyh.ttf` is not Microsoft YaHei despite the filename but
  Droid Sans Fallback carrying an Ascender Corporation EULA reading *"you may not copy this font
  software"*. Neither belonged in an MIT package.

- **`Helpers\Concerns\InteractsWithGeo`** and `Helper::distanceBetween()` — moved to
  `laranail/atlas`, where the result is a `Distance` carrying its own unit rather than a float whose
  unit was set by a string argument several lines earlier.

- **`Traits\HasAvatar`** — moved with the Avatar module.

- **`Toolkit::config()` — the runtime `ConfigManager` moved to `laranail/package-tools`.**
  That package already owned the config file resolver, merger, validator and
  pattern resolver, so config machinery had two homes and two `ConfigMerger`
  classes with the same short name and the same four-method API. Worse, two
  container-resolvable services held **opposite** semantics over `config()` —
  `ConfigService::merge()` yields to the app, `ConfigManager::override()` does
  not — and provider boot order silently decided which won.

  `Services\ConfigManager`, `Services\Contracts\ConfigManagerInterface`,
  `Support\ConfigMerger` and `Exceptions\ConfigException` are gone;
  `ConfigException` had exactly one thrower, so catching it was already dead code.

- **`Toolkit::pythonApi()` — the Python client moved to `laranail/python`.** The
  HTTP client was a third of the problem; the new package is a bidirectional
  bridge with a hardened local-process transport and HMAC-signed inbound
  callbacks. `Services\PythonApiService`, its contract,
  `Services\PythonServiceDefinition` and `Exceptions\PythonApiException` are
  gone, as is the `laranail.toolkit.python` config block — env var names are
  unchanged, so an existing `.env` keeps working.

  Both are `suggest`-ed rather than required. See
  [UPGRADING.md](UPGRADING.md) for the two behaviour changes that came with the
  moves.

- The `Captcha` module (`src/Modules/Captcha/`), its config file and the `Captcha` facade alias have
  been relocated to [`laranail/captcha`](https://github.com/laranail/captcha), which covers eleven
  providers, environment-scoped credentials, a database-backed settings store and edge bot
  management. `Toolkit::captcha()` is gone with it. See UPGRADING.md.

### Security

- **`clearThirdPartyCache()` recursively deleted whatever a config key pointed
  at.** The method is public and takes a config *key*, so any path that key held
  was handed straight to `deleteDirectory()` — no containment check, no symlink
  check, no dry run. `filesystems.disks.local.root`, `view.compiled`, or simply a
  mistyped key would each have emptied a directory the method has no business
  touching.

  Both shipped callers — `purifier.cachePath` and `debugbar.storage.path` — name
  somewhere inside `storage/`, so that is now the boundary: a path outside it is
  refused and logged rather than cleared. The storage root itself is never
  clearable, only things under it, and a symlink is refused outright because
  following one would empty somewhere the check never approved.

### Added

- **`Macros\MacroableModels`** — a macro registry keyed by model class, reachable
  as `Toolkit::macroableModels()`. `Builder::macro()` is global: register
  `whereActive` and it exists on every model's builder, including the ones where
  it makes no sense. This narrows it — a macro is registered *for a model*, and
  calling it on another fails the way an undefined method should.

  A macro's closure is bound to the model instance **and scoped to its class**,
  so `$this->someProtectedThing` reaches the real member rather than falling
  through `Model::__get()` to an attribute lookup that quietly returns null. That
  is a deliberate difference from a bare `Closure::bind($closure, $model)`, which
  keeps the closure's own scope, and it is what makes accessor-style macros work.

- **`Str::withoutBaseUrl()`** — strips the application's own base URL from a
  string, leaving a relative path. Both `config('app.url')` and `url('')` are
  stripped, because they disagree more often than you would like: `url('')` is
  the *current request's* root, so behind a proxy, on a secondary domain, or in a
  queue worker it is not the canonical app URL the content was stored with.
  Using either alone silently leaves the other's URLs absolute.

- **`FileService::filesInPath()`** — relative paths of the files under a
  directory, non-recursive by default, path-guarded and exception-safe like the
  other probes there.

- **Swappable auth guard** — `Toolkit::withGuard('admin')` returns a scoped clone
  whose `user()` / `userAs()` / `userOrFail()` resolve against that guard, without
  a per-call argument or mutating the shared manager. Resolution order: explicit
  per-call `$guard` → `withGuard()` swap → `config('laranail.toolkit.auth.default_guard')`
  → the framework default. See [docs/auth.md](docs/auth.md).

### Changed — breaking

- **`Services\Contracts\FileServiceInterface` gains `filesInPath()`.** Anything
  implementing that contract directly must add the method. Consumers resolving
  it from the container are unaffected.

[Unreleased]: https://github.com/laranail/toolkit/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/laranail/toolkit/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/laranail/toolkit/releases/tag/v0.1.0
