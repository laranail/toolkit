# Utilities

Eleven focused utility classes under
`Simtabi\Laranail\Toolkit\Utilities`. Instance-based utilities are bound in the
container (resolve with `app(...)` or constructor injection); the others expose
static methods. Each can also be published into `app/Utilities/` — see
[installation](installation.md).

## CachingUtil (instance)

Taggable-store caching with a configured default expiration.

```php
$cache = app(CachingUtil::class);
$value = $cache->cache('key', $data, minutes: 30, tags: ['reports']);
$cache->get('key', default: null);
$cache->forget('key');
```

Constructor: `__construct(int $defaultExpiration, array $defaultTags)` — wired
from `config('laranail.toolkit.cache')`.

## ConfigUtil (instance)

Read/write dynamic settings stored in JSON files or app config.

```php
$config = app(ConfigUtil::class);
$config->getAllSettings($path, $key);
$config->getSetting('feature.enabled');
$config->setSetting('feature.enabled', true);
$config->getAllAppSettings();
```

## FeatureToggleUtil (static)

```php
FeatureToggleUtil::isEnabled('example_feature'); // bool
```

Honours per-user and per-environment overrides — see [configuration](configuration.md).

## FilteringUtil (static)

Filter a collection by operator (`equals`, `contains`, `starts_with`,
`ends_with`).

```php
$matches = FilteringUtil::filter($collection, 'name', 'contains', 'lara');
```

## LoggingUtil (static)

Structured logging with context, keyed off the `LogLevel` enum
(`Debug`, `Info`, `Warning`, `Error`, `Critical`).

```php
LoggingUtil::info('User registered', ['id' => $user->id]);
LoggingUtil::error('Import failed', ['file' => $name], channel: 'imports');
LoggingUtil::log(LogLevel::Warning, 'Low disk');
```

## PaginationUtil (static)

```php
$page  = PaginationUtil::paginate($items, perPage: 15, currentPage: 1);
$query = PaginationUtil::paginateQuery(Post::query(), perPage: 25);
```

## QueryParameterUtil (static)

Parse and filter request query params against an allow-list.

```php
$params = QueryParameterUtil::parse($request, ['status', 'sort', 'page']);
```

## RateLimiterUtil (instance)

Thin wrapper over Laravel's rate limiter.

```php
$limiter = app(RateLimiterUtil::class);

if ($limiter->attempt("login:{$ip}", maxAttempts: 5, decayMinutes: 15)) {
    // allowed
}

$limiter->remaining($key, 5);
$limiter->availableIn($key);
$limiter->tooManyAttempts($key, 5);
$limiter->hit($key, decaySeconds: 60);
$limiter->clear($key);
```

## SchedulerUtil (instance)

Inspect the scheduler and find overdue tasks.

```php
$scheduler = app(SchedulerUtil::class);
$summary   = $scheduler->getScheduleSummary();
$overdue   = $scheduler->hasOverdueTasks();
```

## EnvironmentUtil (static)

Environment predicates over the app's own resolver — adds the named buckets and
`isNonProduction()` aggregate the framework does not ship.

```php
EnvironmentUtil::isLocal();            // local / staging / development
EnvironmentUtil::isProduction();
EnvironmentUtil::isNonProduction();
EnvironmentUtil::isTesting();
EnvironmentUtil::isEnvironment(['beta', 'alpha']);
EnvironmentUtil::isRunningUnitTests();
EnvironmentUtil::current();            // 'testing'
```

## AuthUtil (instance, per guard)

Typed accessor for a single named guard (named `AuthUtil` to avoid the `Auth`
facade collision). Built via the `for()` factory, not the container.

```php
$auth = AuthUtil::for('web');

$auth->check();   // bool
$auth->user();    // ?Authenticatable
$auth->id();      // int|string|null
$auth->email();   // ?string
$auth->guard();   // 'web'
```

> Session-backed filter-key + JavaScript-cookie helpers now live in the
> injectable `SessionService` (see [helpers.md](helpers.md#session-service)),
> not in a static utility.

[← Docs index](../README.md#documentation)
