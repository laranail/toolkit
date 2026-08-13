# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed

- The `Captcha` module (`src/Modules/Captcha/`), its config file and the `Captcha` facade alias have
  been relocated to [`laranail/captcha`](https://github.com/laranail/captcha), which covers eleven
  providers, environment-scoped credentials, a database-backed settings store and edge bot
  management. `Toolkit::captcha()` is gone with it. See UPGRADING.md.

## [Unreleased]

## [0.1.0] - 2026-07-12

Initial public release. Folded in during the pre-stable phase:

### Added

- **Swappable auth guard** — `Toolkit::withGuard('admin')` returns a scoped clone
  whose `user()` / `userAs()` / `userOrFail()` resolve against that guard, without
  a per-call argument or mutating the shared manager. Resolution order: explicit
  per-call `$guard` → `withGuard()` swap → `config('laranail.toolkit.auth.default_guard')`
  → the framework default. See [docs/auth.md](docs/auth.md).

### Fixed

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

[Unreleased]: https://github.com/laranail/toolkit/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laranail/toolkit/releases/tag/v0.1.0
