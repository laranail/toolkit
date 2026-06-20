# Changelog

All notable changes to `laranail/toolkit` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- `SECURITY.md`, `CHANGELOG.md`, `.editorconfig`.
