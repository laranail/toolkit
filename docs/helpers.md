# Static helpers

Six focused, **stateless** helper classes under
`Simtabi\Laranail\Toolkit\Helpers`. Every method is `static` — call them directly
(`XHelper::uuid()`), no container resolution or injection required. They hold the
genuinely-reusable functionality rescued from the legacy monolith's grab-bag
helpers, regrouped by concern.

> These are deliberately **not** fronted by the [`Toolkit` facade](../README.md#unified-entry--the-toolkit-facade)
> — they are pure static utilities, so call them by class directly.

## XHelper

General-purpose array / string / date / misc helpers.

```php
use Simtabi\Laranail\Toolkit\Helpers\XHelper;

XHelper::arrayTrim(['  a ', 'b ']);              // ['a', 'b']
XHelper::arrayFlatten($nested);                  // single level of leaf values
XHelper::arrayToDotNotation('a[b][c]');          // 'a.b.c'
XHelper::strBetween('[tag]hi[/tag]', '[tag]', '[/tag]'); // 'hi'
XHelper::strSlugify('Héllo World');              // 'hello-world'
XHelper::ucWords('śćż leçon');                   // mb-aware title case
XHelper::usernameFromEmail('Jane.Doe@x.io');     // 'Jane.Doe'
XHelper::emailFromUsername('jane', 'acme.test'); // 'jane@acme.test'
XHelper::nameToUsernames('Imani', 'Manyara');    // suggestion list
XHelper::carbonParse('2026-01-02');              // '2026-01-02 00:00:00'
XHelper::carbonHumanDiff($date);                 // '3 days ago'
XHelper::uuid();                                 // a UUID string
XHelper::escapeHtml($dirty);                     // XSS-safe HtmlString
XHelper::classBasename($model);                  // short class name
XHelper::randomIntExcept(1, 6, [3]);             // 1,2,4,5 or 6
XHelper::faker('en_US');                         // a Faker generator
```

## SystemHelper

Read-only runtime / environment introspection.

```php
use Simtabi\Laranail\Toolkit\Helpers\SystemHelper;

SystemHelper::parseMemoryLimit('256M');          // 268435456 (bytes; -1 = unlimited)
SystemHelper::memoryLimit();                      // '256M'
SystemHelper::memoryUsage();                      // ['current' => …, 'peak' => …]
SystemHelper::phpVersion();                       // '8.4.3'
SystemHelper::isPhpVersionSupported('8.3');       // bool
SystemHelper::isCli();                            // bool
SystemHelper::isHttps();                          // bool (alias: isSslInstalled())
SystemHelper::composer();                         // app composer.json as array (never throws)
SystemHelper::composerPackageVersion('laravel/framework'); // declared constraint or null
SystemHelper::systemInfo();                       // PHP/OS/SAPI/Laravel/env snapshot
SystemHelper::serverEnv();                        // read-only server settings snapshot
```

## FileHelper

Pure file-**name** / size inspection (no filesystem writes).

```php
use Simtabi\Laranail\Toolkit\Helpers\FileHelper;

FileHelper::formatFileSize(1024);                 // '1 KB'
FileHelper::extension('/a/b/photo.JPG');          // 'jpg'
FileHelper::filenameWithoutExtension('photo.jpg');// 'photo'
FileHelper::isImage('photo.png');                 // true
FileHelper::sanitizeFilename($uploadedName);      // strips separators / null bytes
```

`sanitizeFilename()` cleans a file **name** (apply after `basename()`, before
storing an uploaded name) — it is not a path sanitizer.

## DbHelper

Safe, **read-only** database introspection — every method is exception-safe and
never mutates the application's connections.

```php
use Simtabi\Laranail\Toolkit\Helpers\DbHelper;

DbHelper::canConnect();                           // bool — default connection reachable?
DbHelper::tableExists('users');                   // bool
DbHelper::columnExists('users', 'email');         // bool
DbHelper::connectionNames();                      // ['mysql', 'sqlite', …]

DbHelper::canConnectWith([                         // probe an ad-hoc config safely
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'database' => 'probe',
    'username' => 'root',
    'password' => '',
]);
```

> **`canConnectWith()` safety:** it is the safe replacement for the dropped
> `setDatabaseCredentials`. It registers a throwaway, **uniquely-named** temporary
> connection, opens its PDO inside a `try`/`catch`, and **always** purges and
> unsets the temp config in a `finally` — so the default connection is never
> mutated and credentials are never logged. Failure is returned as `false`, never
> thrown.

## GeoHelper

Native geospatial math (no third-party geo dependency).

```php
use Simtabi\Laranail\Toolkit\Helpers\GeoHelper;

GeoHelper::distanceBetween(-1.2921, 36.8219, -4.0435, 39.6682);        // km
GeoHelper::distanceBetween($lat1, $lng1, $lat2, $lng2, unit: 'mi');    // miles
```

Great-circle (Haversine) distance, returned in `$unit` (`km` | `mi` | `m` | `nmi`).

## ConsoleHelper

Styled output onto a Symfony console stream.

```php
use Simtabi\Laranail\Toolkit\Helpers\ConsoleHelper;

ConsoleHelper::write($output, 'info', 'Done.', 'All green.');
// emits <info>Done.</info> and <info>All green.</info>
```

Wraps each line in the given Symfony style tag (`info`, `comment`, `error`, …).

[← Docs index](../README.md#documentation)
