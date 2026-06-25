# Utilities

The toolkit's eleven focused helper classes are split by responsibility:

- **Services** (`Simtabi\Laranail\Toolkit\Services`) — stateful, interface-backed
  helpers bound in the container. Resolve them with `app(...)`, constructor
  injection, or their contract interface.
- **Support** (`Simtabi\Laranail\Toolkit\Support`) — pure, static helpers with no
  container binding; call their static methods directly.

Each can also be published into `app/Services/` or `app/Support/` — see
[installation](installation.md).

## Services (stateful, interface-backed)

### CacheService

Taggable-store caching with a configured default expiration, key namespacing and
a resilient (log-and-fall-back) failure mode. Bound to
`Services\Contracts\CacheRepositoryInterface`.

```php
$cache = app(CacheService::class);
$value = $cache->cache('key', $data, minutes: 30, tags: ['reports']);
$cache->get('key', default: null);
$cache->forget('key');
$cache->remember('key', fn () => compute(), minutes: 30);
```

Constructor: `__construct(int $defaultExpiration, array $defaultTags, ?LoggerInterface $logger = null, string $namespace = '')`
— wired from `config('laranail.toolkit.cache')`.

### LogService

Structured logging with context, keyed off the `LogLevel` enum
(`Debug`, `Info`, `Warning`, `Error`, `Critical`). Each record is enriched with a
timestamp and the current environment. Bound to
`Services\Contracts\LoggerServiceInterface` (and registered as a singleton).

```php
$log = app(LogService::class);
$log->info('User registered', ['id' => $user->id]);
$log->error('Import failed', ['file' => $name], channel: 'imports');
$log->log(LogLevel::Warning, 'Low disk');
$log->exception($throwable);
```

### SettingsStore

Read/write dynamic settings persisted as a JSON file on a configured disk — a
runtime store, deliberately separate from Laravel's static `config()`. Keys use
dot notation. Bound to `Services\Contracts\SettingsStoreInterface`.

```php
$settings = app(SettingsStore::class);
$settings->all();
$settings->get('feature.enabled', false);
$settings->has('feature.enabled');
$settings->set('feature.enabled', true);
$settings->forget('feature.enabled');
```

### RateLimiterService

Thin wrapper over Laravel's rate limiter. Bound to
`Services\Contracts\RateLimiterServiceInterface` (named `RateLimiterService` to
avoid colliding with Illuminate's `RateLimiter`).

```php
$limiter = app(RateLimiterService::class);

if ($limiter->attempt("login:{$ip}", maxAttempts: 5, decayMinutes: 15)) {
    // allowed
}

$limiter->remaining($key, 5);
$limiter->availableIn($key);
$limiter->tooManyAttempts($key, 5);
$limiter->hit($key, decaySeconds: 60);
$limiter->clear($key);
```

### SchedulerService

Inspect the scheduler and find overdue tasks. Bound to
`Services\Contracts\SchedulerServiceInterface`.

```php
$scheduler = app(SchedulerService::class);
$summary   = $scheduler->getScheduleSummary();
$overdue   = $scheduler->hasOverdueTasks();
```

## Support (pure, static)

### AuthHelper (per guard)

Typed accessor for a single named guard (named `AuthHelper` to avoid the `Auth`
facade collision). Built via the `for()` factory, not the container.

```php
$auth = AuthHelper::for('web');

$auth->check();   // bool
$auth->user();    // ?Authenticatable
$auth->id();      // int|string|null
$auth->email();   // ?string
$auth->guard();   // 'web'
```

> Session-backed filter-key + JavaScript-cookie helpers now live in the
> injectable `SessionService` (see [helpers.md](helpers.md#session-service)),
> not in a static utility.

### Environment

Environment predicates over the app's own resolver — adds the named buckets and
`isNonProduction()` aggregate the framework does not ship.

```php
Environment::isLocal();            // local / staging / development
Environment::isProduction();
Environment::isNonProduction();
Environment::isTesting();
Environment::isEnvironment(['beta', 'alpha']);
Environment::isRunningUnitTests();
Environment::current();            // 'testing'
```

### CollectionFilter

Filter a collection by operator (`equals`, `contains`, `starts_with`,
`ends_with`).

```php
$matches = CollectionFilter::filter($collection, 'name', 'contains', 'lara');
```

### Pagination

```php
$page  = Pagination::paginate($items, perPage: 15, currentPage: 1);
$query = Pagination::paginateQuery(Post::query(), perPage: 25);
```

### QueryParameters

Parse and filter request query params against an allow-list.

```php
$params = QueryParameters::parse($request, ['status', 'sort', 'page']);
```

### FeatureToggle

```php
FeatureToggle::isEnabled('example_feature'); // bool
```

Honours per-user and per-environment overrides — see [configuration](configuration.md).

[← Docs index](../README.md#documentation)
