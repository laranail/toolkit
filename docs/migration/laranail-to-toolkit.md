# `Laranail::` → `Toolkit::` migration map

The legacy 48-method `Simtabi\Laranail\Foundation\Laranail` service-locator is
**dropped** — it was a constructor-injected god-object fronting eighteen
services, the classic service-locator anti-pattern. Its clean replacement is the
small, typed **`Toolkit`** facade (`Simtabi\Laranail\Toolkit\Facades\Toolkit`),
which resolves one feature module at a time through the container, plus the
static **`Helper`** convenience class (`Simtabi\Laranail\Toolkit\Helpers\Helper`)
for the dep-free string/array/date/system primitives.

Every legacy `Laranail::method()` is accounted for below. The "New home"
column is one of:

- **`Toolkit::service()->...`** — resolve a module via the facade, then chain.
- **`Helper::...`** — a static call on the convenience class (static→static).
- **native** — drop the wrapper; call the Laravel / PHP primitive directly.
- **DROPPED** — removed by design; the safe alternative is named.

> For the full per-symbol accounting see **[MIGRATION.md](MIGRATION.md)** and the
> drop-rationale subset in **[dropped.md](dropped.md)**.

## Authentication

| Legacy | New home |
|--------|----------|
| `Laranail::authHelper($guard)` | `Toolkit::auth()->setGuard($guard)` (typed `AuthenticationContextService`; or `Utilities\AuthUtil::for($guard)`). |
| `Laranail::username()` | `Toolkit::auth()->getUser()?->username` / `Utilities\AuthUtil::authHelper()->username()`. |
| `Laranail::isUserExists($value, $model, $key)` | `Utilities\AuthUtil::for($guard)->userExists($value, $key)` — native `Model::query()->where()->exists()`. |

## Files

| Legacy | New home |
|--------|----------|
| `Laranail::checkIfFileExistsInStorage($path)` | native `Storage::exists($path)`. |
| `Laranail::getFileAsObject($path)` | native `new Illuminate\Http\File($path)` (or `Traits\FileProcessingTrait`). |
| `Laranail::pathToUploadedFileInstance($path, $test)` | native `new Illuminate\Http\UploadedFile($path, basename($path), null, null, $test)`. |
| `Laranail::clearLogFiles()` | `Toolkit::db()->clearLogFiles()` (`DatabaseServiceInterface`). |
| `Laranail::deleteStorageSymlink()` | `Toolkit::db()->deleteStorageSymlink()` (`DatabaseServiceInterface`). |

## Cache

| Legacy | New home |
|--------|----------|
| `Laranail::clearCache()` | `Toolkit::db()->clearCache()` (`DatabaseServiceInterface`), or native `Cache::flush()`. |
| `Laranail::cache($name, $cb, $ttl)` | native `Cache::remember($name, $ttl, $cb)`. |

## Routes

| Legacy | New home |
|--------|----------|
| `Laranail::getAppUrl()` | `Toolkit::route()->getAppUrl()` (`RouteServiceInterface`), or native `config('app.url')`. |
| `Laranail::getCurrentRouteInfo()` | `Toolkit::route()->getCurrentRouteInfo()`. |
| `Laranail::isCurrentRoute($name)` | `Toolkit::route()->isCurrentRoute($name)`, or native `Route::currentRouteNamed($name)`. |
| `Laranail::getActiveCssClassForRoute($name, $class)` | `Toolkit::route()->getActiveCssClassForRoute($name, $class)`. |

## Validation (view layer)

| Legacy | New home |
|--------|----------|
| `Laranail::getErrorBagMessage($key, ...)` | `Toolkit::validation()->getErrorBagMessage($key, ...)` (`ValidationServiceInterface`; every value `e()`-escaped). |
| `Laranail::getCheckboxStatus($old, $key)` | `Toolkit::validation()->getCheckboxStatus($old, $key)`. |

## Database

| Legacy | New home |
|--------|----------|
| `Laranail::isValidDatabaseConnection($table)` | `Toolkit::validation()->isValidDatabaseConnection($table)` (`Schema::hasTable`), or `Helper::tableExists($table)`. |
| `Laranail::setDatabaseCredentials($creds)` | **DROPPED** — mutated live `config()` globally + raw `SHOW TABLES` (credential-leak-prone). Probe safely with `Helper::canConnectWith($config)` instead. |
| `Laranail::registerModelObserver($model, $observer)` | native `#[ObservedBy]` attribute, or `Toolkit::model()->registerModelObserver($model, $observer)`. |

## Session / query-string filters

| Legacy | New home |
|--------|----------|
| `Laranail::existsInFilterKey($key, $value)` | `Toolkit::session()->existsInFilterKey($key, $value)` (or inject `SessionServiceInterface`). |
| `Laranail::joinInFilterKey(...$value)` | `Toolkit::session()->joinInFilterKey(...$value)`. |
| `Laranail::removeFromFilterKey($key, $old, $value)` | `Toolkit::session()->removeFromFilterKey($old, $value)`. |
| `Laranail::saveJavaScriptCookies($name, $duration)` | `Toolkit::session()->saveJavaScriptCookies($name, $duration)`. |

## System / environment

| Legacy | New home |
|--------|----------|
| `Laranail::getComposerArray()` | `Helper::composer()`. |
| `Laranail::getSystemEnv()` | `Helper::systemInfo()`. |
| `Laranail::isSslIsInstalled()` | `Helper::isSslInstalled()`. |
| `Laranail::getServerEnv()` | `Helper::serverEnv()`. |
| `Laranail::environment()` | native `app()->environment()` / `Utilities\EnvironmentUtil`. |

## Utility / string / array

| Legacy | New home |
|--------|----------|
| `Laranail::arrayToDotNotation($expr)` | `Helper::arrayToDotNotation($expr)`. |
| `Laranail::sortItemWithChildren($list, ...)` | `Toolkit::model()->sortItemWithChildren($list, ...)`, or the `toTree()` collection macro. |
| `Laranail::mapKeyValuePairArray($data)` | `collect($data)->mapKeyValuePairs()` (`Macros\CollectionMacros`). |
| `Laranail::generateLivewireComponentKey($name)` | `Toolkit::livewire()->generateComponentKey($name)` (`LivewireServiceInterface`). |
| `Laranail::random($from, $to, $except)` | `Helper::randomIntExcept($from, $to, $except)`. |
| `Laranail::ucWords($string)` | `Helper::ucWords($string)`. |
| `Laranail::generateUsername($email)` | `Helper::usernameFromEmail($email)`. |
| `Laranail::generateEmailFromUsername($username)` | `Helper::emailFromUsername($username)`. |
| `Laranail::faker()` | `Helper::faker()` (static→static), or native `fake()`. |
| `Laranail::writeToConsoleOutput($style, $output, ...)` | `Helper::write($output, $style, ...)`. |
| `Laranail::sortSearchResults($collection, $terms, $column)` | `collect($collection)->sortSearchResults($terms, $column)` (`Macros\CollectionMacros`). |
| `Laranail::html($dirty, $config)` | `Helper::escapeHtml($dirty)`. |

## Model

| Legacy | New home |
|--------|----------|
| `Laranail::getModelItem($key, $model, $default)` | `Toolkit::model()->getModelItem($model, $key, $default)` (`ModelService`). |
| `Laranail::eloquent2selectbox($data, ...)` | `Toolkit::model()->eloquent2selectbox($data, ...)`. |
| `Laranail::getClassNameFromClass($class)` | `Helper::classBasename($class)`, or native `class_basename($class)`. |
| `Laranail::logError($exception)` | `Utilities\LoggingUtil::exception($exception)`, or native `Log::error(...)`. |

## Sub-service accessors (returned the injected service)

| Legacy | New home |
|--------|----------|
| `Laranail::package()` | **DROPPED** — `PackageService` god-class superseded by **`laranail/package-tools`** (`Package` + `PackageServiceProvider`). See [dropped.md](dropped.md). |
| `Laranail::httpConfig()` | `Toolkit::http()` (`HttpConfigurationServiceInterface`). |
| `Laranail::formatter()` | **DROPPED** — `ModelFormatterService` was mostly stubs; the working formatters live in `Traits\HasFormatters`. |
| `Laranail::livewire()` | `Toolkit::livewire()` (`LivewireServiceInterface`). |

[← Docs index](../../README.md#documentation)
