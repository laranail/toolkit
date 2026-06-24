# Restore candidates — dropped helpers worth recovering

Some functionality was DROPPED during the migration that, on review, is genuinely
useful as **static helper convenience methods**. This is the worklist for recovering
it into a small set of focused static helpers under `src/Helpers/` — built piece by
piece, ticking each off and flipping the source entry `dropped → merged` in
`tests/Fixtures/Legacy/removed-symbols.json` (then regenerating the ledger with
`php tests/Fixtures/Legacy/build-ledger.php`).

Rules carried over: never duplicate something the toolkit already ships, never
re-introduce a security hazard (no `config()` mutation, no credential logging), fix
legacy bugs on the way, test everything. Source method bodies were read directly.

---

## RESTORE — grouped by target helper

### `Helpers\SystemHelper` (new)  — sources: `Laravel\Services\SystemService`, `Foundation\Services\SystemService`
- [x] `parseMemoryLimit(string): int` — "256M" → bytes (g/m/k suffix); `-1` → unlimited. *(pure)*
- [x] `memoryUsage(): array` — current/peak bytes + human-formatted (via `FileHelper::formatFileSize`).
- [x] `memoryLimit(): string` — `ini_get('memory_limit')`.
- [x] `phpVersion(): string` / `isPhpVersionSupported(string $min): bool` — `version_compare`.
- [x] `isCli(): bool` — `PHP_SAPI === 'cli'`.
- [x] `isHttps(): bool` — safe `$_SERVER['HTTPS']` check (renamed from `isSslInstalled`).
- [x] `serverEnv(): array` — read-only bundle of ini/runtime settings (sapi, extensions, limits, tz, disk space). No mutation.

### `Helpers\FileHelper` (new)  — source: `Foundation\Services\FileHelperService`
- [x] `formatFileSize(int $bytes, int $precision = 2): string` — human-readable size.
- [x] `isImage(string $path): bool` — image extension check. **Bug fixed:** legacy used `Arr::has($list,$value)` (key check) → `in_array($value,$list,true)`.
- [x] `sanitizeFilename(string): string` — strip path separators/null-bytes, replace unsafe chars. Security-relevant; pairs with `Support\FilePathGuard` (paths) — this guards *names*.
- [x] `extension(string $path): string` / `filenameWithoutExtension(string $path): string` — `pathinfo` wrappers.

### `Helpers\DbHelper` (new)  — safe DB checks (motivated by "checking db connection")
- [x] `canConnect(?string $connection = null): bool` — opens the PDO in try/catch; **never mutates `config()`, never logs credentials**.
- [x] `tableExists(string, ?string $connection = null): bool` / `columnExists(...)` — `Schema` wrappers, exception-safe.
- [x] `connectionNames(): array` — configured connection names.

> The unsafe legacy `ValidationService::isValidDbCredentials` / `setDatabaseCredentials`
> are **deliberately NOT restored** — they wrote user-supplied credentials into live
> `config()` and could log them. `DbHelper::canConnect()` is the safe replacement.

---

## SKIP — stays dropped (with reason)

**Already in the toolkit** (no restore): `ucWords`, `usernameFromEmail`/`generateUsername`,
`emailFromUsername` (→ `Helpers\XHelper`); `sortItemWithChildren` (→ `Services\ModelService`);
`sortSearchResults`, `mapKeyValuePairArray` (→ `Services\*` / superior versions);
`clearLogFiles`, `deleteStorageSymlink` (→ `Services\DatabaseService`, with containment guards);
`formatBytes`, disk/extension/writable probes (→ `Support\Diagnostics\RequirementsDiagnostics`);
all `ValidationService` HTML/checkbox/old-input helpers (→ `Services\ValidationService`).

**Native one-liners** (use the primitive): `arrayToDotNotation` (`Arr::dot`),
`getRandomIdFromCollection` (`->random()`), `faker` (`fake()`), `html` (`e()`/`HtmlString`),
`getClassName` (`class_basename`), `getFileAsObject`/`pathToUploadedFileInstance` (direct ctor),
`exists`/`validateFileSize` (`File::exists`/`File::size`), `writeToConsoleOutput`,
`fetchKeyedArrayValues`, `getExtension`/`getFilenameWithoutExtension` *(restored anyway as a useful pair on FileHelper)*.

**Broken / unsafe** (do NOT restore): `ValidationService::isValidDbCredentials` +
`setDatabaseCredentials` (config mutation + credential logging); `UtilityService::random`
(unbounded recursive retry); `SessionService::*FilterKey` + `saveJavaScriptCookies`
(non-idiomatic/incomplete); `DatabaseFileService::handleImport` (stub).

**Out of scope:** `LivewireComponentService::*`, `PackageService::*` (→ `ToolkitServiceProvider` /
`laranail/package-tools`), `ImportDatabaseService`, `ResponseBuilderService` (→ `ApiResponseTrait`),
`ConditionalRunner::*` (console-task runner), `Atlas\Countries`/`Languages` (→ `rinvex/countries`),
`DiskSpaceValidator` (≈ `RequirementsDiagnostics` + `disk_free_space()`),
`generateRandomSalutation`/`generateLivewireComponentKey` (domain/framework-specific),
`AuthenticationService::*` (→ `Utilities\AuthUtil`).

---

## G8 — convenience restoration + reusable base classes (2026-06-23, all ✅ done)

The owner's "it's a developer toolkit — keep the convenience wrappers" pass. All native under
the hood, no duplication; sources flipped `dropped → merged` (or the entry deleted = MIGRATED for
same-named restorations), verifier stays green. **This section supersedes the SKIP list above where
they conflict** (G8 reversed several earlier "native one-liner" / "broken" calls per owner).

### Convenience methods (enhanced existing classes)
- [x] `Helpers\XHelper`: `arrayToDotNotation`, `escapeHtml` (legacy `html`), `classBasename`, `randomIntExcept` (bounded — fixes legacy unbounded recursion), `faker`.
- [x] `Helpers\SystemHelper`: `composer`, `composerPackageVersion`, `systemInfo`, `isSslInstalled` (alias of `isHttps`).
- [x] `Helpers\DbHelper`: `canConnectWith` — **safe** ephemeral-connection probe (replaces the unsafe `setDatabaseCredentials`; no `config()` mutation, no credential logging).
- [x] `Utilities\SessionHelper` (new): `existsInFilterKey`, `joinInFilterKey`, `removeFromFilterKey`, `saveJavaScriptCookies` (clean idiomatic re-implementation).
- [x] `Utilities\LoggingUtil`: `exception()` (legacy `logError`).
- [x] `Utilities\AuthUtil`: `username`, `userExists`, `authHelper` alias.
- [x] `Services\ModelService`: `getModelItem` (`data_get`).
- [x] `Macros\CollectionMacros`: `mapKeyValuePairs` (fixes the broken legacy `Collection->select`), `sortSearchResults` (relevance scoring).
- [x] `Helpers\GeoHelper` (new): `distanceBetween` — native Haversine (restores `DistanceBetween`, no pheg).
- [x] `Helpers\ConsoleHelper` (new): `write` (legacy `writeToConsoleOutput`).

### New HTTP classes
- [x] `Http\Requests\ApiRequest` — JSON-envelope validation failures (extends `BaseRequest`).
- [x] `Http\Middleware\EmailObfuscatorMiddleware` — native, no pheg; opt-in alias `email.obfuscate`.

### Reusable base classes (real shared code, for future reuse)
- [x] `Http\Controllers\BaseController` — abstract base (`AuthorizesRequests`+`ValidatesRequests`+`ApiResponseTrait`); `CrudController` now extends it.
- [x] `Jobs\BaseJob` (queue defaults + `failed()` logging), `Listeners\BaseListener` (`shouldHandle()` gate), `Observers\BaseObserver`, `Events\BaseEvent`.

### Still dropped (with reason)
`setDatabaseCredentials` as-is (security → `DbHelper::canConnectWith`); `HasPackageTools`
(ServiceProvider / `laranail/package-tools` concern); `HasLivewire` / `generateLivewireComponentKey` /
`livewire` (Livewire-specific); `LicenseListener` + the never-dispatched `Shared\Events\*` stubs.

---

## Done condition (same as the migration)
Each ticked item: source flipped `dropped → merged` (target = the helper) in
`removed-symbols.json`, helper method + test added, all gates green, and
`php tests/Fixtures/Legacy/build-ledger.php --verify` still exits 0.
