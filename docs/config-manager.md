# Config manager

`ConfigManager` is a fluent, chainable manager for mutating Laravel configuration
**at runtime**. Resolve it by contract
`Services\Contracts\ConfigManagerInterface` (preferred) or via `Toolkit::config()`.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Services\Contracts\ConfigManagerInterface;

Toolkit::config()
    ->override('horizon.path', '/ops')
    ->merge('app', ['providers' => [MyProvider::class]])   // true deep merge
    ->when(app()->isLocal(), fn ($c) => $c->override('app.debug', true))
    ->remove('services.unused');
```

Every mutator returns `$this`. All access flows through the injected config
repository (no facade / container mixing), and it is **runtime-only** — for values
that must persist across requests use the [settings store](utilities.md), not this
manager.

## Read / write

| Method | Returns | |
|--------|---------|---|
| `get(string $key, mixed $default = null)` | `mixed` | |
| `has(string $key)` | `bool` | |
| `set(string $key, mixed $value)` | `static` | |
| `override(string $key, mixed $value)` | `static` | Semantic alias of `set()`. |
| `setIfMissing(string $key, mixed $value)` | `static` | |
| `setMany(array $values)` / `overrideMany(array $values)` | `static` | |
| `remove(string $key)` / `forget(string $key)` | `static` | Nested keys fully removed; a top-level key is nulled. |
| `all()` | `array` | |

## Merge, push, transform

| Method | Notes |
|--------|-------|
| `merge(string $key, array $values)` | **Deep** merge (uses `Support\ConfigMerger`, not `array_merge_recursive`, so scalars are overwritten, not duplicated into arrays). |
| `push(string $key, mixed $value)` / `prepend(string $key, mixed $value)` | Append / prepend to an array value. |
| `overrideSection(array $source, string $sectionKey, string $targetPath)` | Copy a section of `$source` onto a config path. |
| `transform(string $key, Closure $fn)` | Replace a value with `$fn($current)`. |
| `each(string $key, Closure $fn)` | Map every item of an array value. |

> `ConfigMerger::deepMerge` merges numeric-keyed lists index-by-index; when you
> want list concatenation or wholesale replacement use `mergeWithStrategy(...,
> 'append'|'replace')` on `Support\ConfigMerger` directly.

## Load from files

| Method | Notes |
|--------|-------|
| `setBasePath(string $path)` | Base for relative file paths. |
| `loadAndOverride(string $configKey, string $filePath)` | Require a file and set each value under `$configKey`. Throws `Exceptions\ConfigException` when the file is missing or not an array. |
| `loadPackageConfig(string $key, string $folder = 'config/packages')` | |
| `loadPackageConfigs(array $keys, string $folder = 'config/packages')` | |
| `loadConfigFile(string $file)` | Return a config file's raw array (lenient: `[]` when absent). |

## Conditionals & logging

| Method | Notes |
|--------|-------|
| `when(bool\|Closure $cond, Closure $cb)` / `unless(...)` | Run `$cb($this)` conditionally. |
| `inEnvironment(string\|array $envs, Closure $cb)` | Run only in the given environment(s). |
| `whenHas(string $key, Closure $cb)` | Run `$cb($this, $value)` when the key exists. |
| `withLogging(bool $on = true)` / `getLog()` / `clearLog()` | Record each operation (a genuine null value is logged) for debugging. |

```php
Toolkit::config()
    ->withLogging()
    ->setBasePath(base_path('modules/blog'))
    ->loadPackageConfig('blog')
    ->inEnvironment('production', fn ($c) => $c->override('blog.cache', true));
```

---

[← Docs index](../README.md#documentation)
