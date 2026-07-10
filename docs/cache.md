# Cache

`CacheService` is the toolkit's single cache entry point — one surface for both
application **data caching** and framework **cache maintenance**. Resolve it by
contract `Services\Contracts\CacheRepositoryInterface` (preferred) or via
`Toolkit::cache()`.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;

$cache = Toolkit::cache();                          // or inject CacheRepositoryInterface
```

Every method is **resilient**: a backend failure is logged and swallowed (data
reads/writes fall back; maintenance ops never throw), so a cache call can never
break a request.

## Data cache

A namespaced, optionally-tagged wrapper over the framework store. Namespacing is
applied consistently across reads, writes and forgets, so a `put()` → `get()` →
`forget()` round-trip always aligns (the default empty namespace is an identity —
behaviour is unchanged unless you configure `laranail.toolkit.cache.namespace`).

| Method | Returns | |
|--------|---------|---|
| `cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null)` | `mixed` | Store and return `$data` (uses constructor default tags/expiry). |
| `get(string $key, mixed $default = null)` | `mixed` | Read (namespace- and tag-aware). |
| `put(string $key, mixed $value, ?int $minutes = null)` | `bool` | Store; `false` on failure. |
| `forget(string $key)` | `void` | Remove the matching write. |
| `remember(string $key, callable $cb, ?int $minutes = null)` | `mixed` | Return cached or compute-store-return. |
| `rememberForever(string $key, callable $cb)` | `mixed` | As above, no expiry. |
| `many(array $keys, mixed $default = null)` | `array` | Batch read, keyed by original keys. |
| `increment(string $key, int $by = 1)` | `int\|false` | |
| `decrement(string $key, int $by = 1)` | `int\|false` | |
| `tags(array $tags)` | `static` | A clone scoped to `$tags` for grouped invalidation. |
| `keyFromRequest()` | `string` | Stable hash of the request input + current URL. |

```php
$user = $cache->remember("user:{$id}", fn () => User::find($id), minutes: 15);

$cache->tags(['reports'])->put('q3', $data);
$cache->tags(['reports'])->get('q3');               // reads back under the same tag scope

$key = $cache->keyFromRequest();                    // request-scoped response caching
```

## Cache maintenance

Clears and rebuilds Laravel's own caches plus config-gated third-party dirs. Each
op dispatches [`CacheEvents`](modules/eventing.md) (clearing → cleared/failed) and
returns `static`, so they chain.

| Method | Clears / does |
|--------|---------------|
| `clearFrameworkCache()` | Flush the whole cache store. |
| `clearConfig()` | Delete the cached config file. |
| `clearRoutes()` | Delete the cached routes file. |
| `clearCompiledViews()` | Delete compiled Blade views. |
| `clearBootstrap()` | Delete the bootstrap cache `*.php`. |
| `clearEvents()` | Delete the cached events file. |
| `clearLogs()` | Delete `storage/logs/*.log`. |
| `clearThirdPartyCache(string $configKey)` | Clear a dir/file named by a config key (skips when unset/absent). |
| `clearPurifier()` / `clearDebugbar()` | Shims for `purifier.cachePath` / `debugbar.storage.path`. |
| `cacheConfig()` | Rebuild the cached config (Closures stripped). |
| `cacheViews()` | Recompile every Blade view. |
| `purgeAll()` | Clear framework + third-party caches and logs in one pass. |

Two orchestrators return a typed `Services\CacheOptimizationResult`
(`success`, `steps`, `errors`) instead of ad-hoc arrays:

| Method | Does |
|--------|------|
| `optimize()` | Clear then rebuild config + views, flush the store. |
| `clearOptimization()` | Clear the config/routes/views optimization caches. |

```php
$cache->clearConfig()->clearRoutes()->cacheConfig();   // fluent chain

$result = $cache->optimize();
if ($result->failed()) {
    report(new RuntimeException('cache optimize incomplete: ' . implode(', ', array_keys($result->errors))));
}
```

## Events

Each maintenance op fires `Modules\Eventing\Events\CacheEvents`. Set
`laranail.toolkit.cache.log_events` to `true` to have the bundled `LogCacheEvents`
listener log the lifecycle. See [eventing](modules/eventing.md).

## Configuration

Defaults live under `laranail.toolkit.cache` — see
[configuration](configuration.md#laranailtoolkitcache).

---

[← Docs index](../README.md#documentation)
