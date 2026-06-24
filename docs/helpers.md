# Static helpers

One focused, **stateless** helper facade — `Simtabi\Laranail\Toolkit\Helpers\Helper`.
Every method is `static` — call them directly (`Helper::uuid()`), no container
resolution or injection required. `Helper` is a `final` class composed of
per-domain `InteractsWith*` traits under
`Simtabi\Laranail\Toolkit\Helpers\Concerns`; the trait split is an internal
organising detail — the public surface is always `Helper::`.

```php
use Simtabi\Laranail\Toolkit\Helpers\Helper;
```

> These are deliberately **not** fronted by the [`Toolkit` facade](../README.md#unified-entry--the-toolkit-facade)
> — they are pure static utilities, so call them on `Helper` directly.

## Arrays

```php
Helper::arrayTrim(['  a ', 'b ']);              // ['a', 'b']
Helper::arrayFlatten($nested);                  // single level of leaf values
Helper::arrayToDotNotation('a[b][c]');          // 'a.b.c'
```

## Strings & identity

```php
Helper::strBetween('[tag]hi[/tag]', '[tag]', '[/tag]'); // 'hi'
Helper::strSlugify('Héllo World');              // 'hello-world'
Helper::ucWords('śćż leçon');                   // mb-aware title case
Helper::usernameFromEmail('Jane.Doe@x.io');     // 'Jane.Doe'
Helper::emailFromUsername('jane', 'acme.test'); // 'jane@acme.test'
Helper::nameToUsernames('Imani', 'Manyara');    // suggestion list
Helper::uuid();                                 // a UUID string
Helper::escapeHtml($dirty);                     // XSS-safe HtmlString
Helper::classBasename($model);                  // short class name
Helper::randomIntExcept(1, 6, [3]);             // 1,2,4,5 or 6 (bounded; throws if impossible)
Helper::faker('en_US');                         // a Faker generator
```

## Dates

```php
Helper::carbonParse('2026-01-02');              // '2026-01-02 00:00:00'
Helper::carbonHumanDiff($date);                 // '3 days ago'
```

## System

Read-only runtime / environment introspection (no `config()` mutation, no I/O
beyond reading PHP/ini/`$_SERVER` state).

```php
Helper::parseMemoryLimit('256M');               // 268435456 (bytes; -1 = unlimited)
Helper::memoryLimit();                          // '256M'
Helper::memoryUsage();                          // ['current' => …, 'peak' => …]
Helper::phpVersion();                           // '8.4.3'
Helper::isPhpVersionSupported('8.3');           // bool
Helper::isCli();                                // bool
Helper::isHttps();                              // bool (alias: isSslInstalled())
Helper::composer();                             // app composer.json as array (never throws)
Helper::composerPackageVersion('laravel/framework'); // declared constraint or null
Helper::systemInfo();                           // PHP/OS/SAPI/Laravel/env snapshot
Helper::serverEnv();                            // read-only server settings snapshot
```

## Files

File-**name** / size inspection plus a few thin, path-guarded filesystem probes.

```php
Helper::formatFileSize(1024);                   // '1 KB'
Helper::extension('/a/b/photo.JPG');            // 'jpg'
Helper::filenameWithoutExtension('photo.jpg');  // 'photo'
Helper::isImage('photo.png');                   // true
Helper::sanitizeFilename($uploadedName);        // strips separators / null bytes

Helper::exists('/srv/app/.env');                // bool — safe path + File::exists
Helper::size('/srv/app/dump.sql');              // int bytes (0 if missing/unsafe)
Helper::lastModified('/srv/app/dump.sql');      // int UNIX ts (0 if missing/unsafe)
Helper::hasAllowedExtension('db.sqlite', ['sql', 'sqlite', 'db']); // true
Helper::fileInfo('/srv/app/dump.sql');          // path/size/extension/name/… or []
```

`sanitizeFilename()` cleans a file **name** (apply after `basename()`, before
storing an uploaded name) — it is not a path sanitizer.

The probes (`exists`, `size`, `lastModified`, `fileInfo`) are read-only and
exception-safe: each rejects `..` traversal segments and null bytes via the
canonical `Support\FilePathGuard` (returning `false`/`0`/`[]` rather than
throwing). `hasAllowedExtension()` is generic — pass whatever allow-list you
need; it lower-cases and strips a leading dot before comparing. Plain
read/write/copy/move/delete are deliberately not wrapped — use the `Storage` /
`File` facades or `Traits\FileProcessingTrait` directly.

## Database

Safe, **read-only** database introspection — every method is exception-safe and
never mutates the application's connections.

```php
Helper::canConnect();                           // bool — default connection reachable?
Helper::tableExists('users');                   // bool
Helper::columnExists('users', 'email');         // bool
Helper::connectionNames();                      // ['mysql', 'sqlite', …]

Helper::canConnectWith([                         // probe an ad-hoc config safely
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

## Geo

Native geospatial math (no third-party geo dependency).

```php
Helper::distanceBetween(-1.2921, 36.8219, -4.0435, 39.6682);        // km
Helper::distanceBetween($lat1, $lng1, $lat2, $lng2, unit: 'mi');    // miles
```

Great-circle (Haversine) distance, returned in `$unit` (`km` | `mi` | `m` | `nmi`).

## Console

Styled output onto a Symfony console stream.

```php
Helper::write($output, 'info', 'Done.', 'All green.');
// emits <info>Done.</info> and <info>All green.</info>
```

Wraps each line in the given Symfony style tag (`info`, `comment`, `error`, …).

[← Docs index](../README.md#documentation)
