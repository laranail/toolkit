# Runtime configurator

`Support\RuntimeConfigurator` is a chainable API for adjusting PHP runtime/INI
settings during heavy operations — memory limit, execution time, arbitrary INI
directives, and disabling debugging tools (Telescope, Xdebug, Clockwork, Debugbar).
Get a fresh builder via `Toolkit::runtime()` or `RuntimeConfigurator::make()`.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;

Toolkit::runtime()
    ->memory('1G')
    ->timeout(0)
    ->disableTelescope()
    ->apply();
```

Original INI values are captured on construction, so the configurator can restore
them. `Toolkit::runtime()` returns a **new** builder each call (it snapshots INI
state), like the security generators.

## Scope a change to a callback

`scope()` applies the settings, runs the callback, and restores in a `finally` —
apply/restore are symmetric (Xdebug is re-enabled, and restore reuses the same
`set_time_limit` / `error_reporting` handling as apply):

```php
$rows = Toolkit::runtime()
    ->forBatchProcessing()               // 2G, no timeout, all debugging off
    ->scope(fn () => $importer->run());
```

## Presets

| Factory | Configures |
|---------|------------|
| `make()` | Empty builder. |
| `forQueueJob()` | 1G memory, no timeout, Telescope off. |
| `forBatchProcessing()` | 2G memory, no timeout, all debugging off. |
| `forImport()` | 1G memory, 30-minute timeout, Telescope off. |
| `forExport()` | 1G memory, 15-minute timeout, Telescope off. |

## Builder methods

| Group | Methods (each returns `self`) |
|-------|-------------------------------|
| Memory | `memory(string)`, `memoryMb(int)`, `memoryGb(int\|float)`, `unlimitedMemory()` |
| Time | `timeout(int)`, `maxExecutionTime(int)`, `noTimeout()`, `timeoutMinutes(int)`, `timeoutHours(int)` |
| Errors | `errorReporting(int)`, `reportAllErrors()`, `reportErrorsOnly()`, `suppressErrors()`, `displayErrors(bool)` |
| Debug tools | `disableTelescope()`/`enableTelescope()`, `disableXdebug()`/`enableXdebug()`, `disableClockwork()`/`enableClockwork()`, `disableDebugbar()`/`enableDebugbar()`, `disableAllDebugging()` |
| INI / uploads | `set(string, mixed)`, `setMany(array)`, `realpathCacheSize(string)`, `realpathCacheTtl(int)`, `postMaxSize(string)`, `uploadMaxFilesize(string)`, `forLargeUploads(string = '100M')` |
| Conditional | `when(bool, callable, ?callable)`, `unless(bool, callable)`, `whenCli(callable)`, `whenWeb(callable)` |
| Logging | `withLogging(?string $channel = null)`, `withoutLogging()` |

## Lifecycle & introspection

| Method | Notes |
|--------|-------|
| `apply()` | Materialise the pending changes. Idempotent — a second call while applied is a no-op. |
| `scope(callable)` | Apply → run → restore (returns the callback result). |
| `restore()` | Restore captured originals and re-enable disabled tools. |
| `isApplied()` / `getPending()` / `getOriginal()` / `getDisabledTools()` / `get(string)` | State accessors. |

Static helpers: `setMemory(string)`, `setTimeout(int)`, `isCli()`,
`hasTelescopeInstalled()`, `hasXdebugLoaded()`, `hasDebugbarInstalled()`,
`hasClockworkInstalled()`.

> Memory **introspection** (usage, peak, formatted sizes) is intentionally not
> duplicated here — read it from the system service:
> `Toolkit::system()->memoryUsage()`.

---

[← Docs index](../README.md#documentation)
