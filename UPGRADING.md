# Upgrade guide

## `Toolkit::config()` moved to `laranail/package-tools`

Config machinery belongs with the package-authoring runtime, which already owned the config file
resolver, merger, validator and pattern resolver under `Services/Config/`. Keeping a second
`ConfigMerger` here meant two classes with the same short name and the same four-method API, free to
drift apart — and two container-resolvable services with **opposite** semantics both authoritative
over `config()`: `ConfigService::merge()` is `mergeConfigFrom`-style where app config wins, while
`ConfigManager::override()` force-overrides. Provider boot order decided which one won.

```diff
+   composer require laranail/package-tools
```

```diff
-   use Simtabi\Laranail\Toolkit\Services\Contracts\ConfigManagerInterface;
+   use Simtabi\Laranail\Package\Tools\Contracts\ConfigManagerInterface;

-   Toolkit::config()->set('services.stripe.key', $key);
+   app(ConfigManagerInterface::class)->set('services.stripe.key', $key);
+   // or the facade package-tools registers:
+   PackageConfig::set('services.stripe.key', $key);
```

- `Toolkit::config()` and `Laranail::config()` are removed.
- `Simtabi\Laranail\Toolkit\Services\ConfigManager`, its contract,
  `Support\ConfigMerger` and `Exceptions\ConfigException` are removed.
  `ConfigException` had exactly one thrower, so a `catch` for it was already dead code.
- The boundary is now documented rather than implicit: `ConfigService` is boot-time merge where the
  **app** wins; `ConfigManager` is runtime override where the **caller** wins.

**One behaviour change.** `remove()` now makes both `get()` and `has()` miss for a top-level key.
Here it could only ever *null* the value — it passed the whole config array as `Repository::set()`'s
key — so `get()` returned null while `has()` still returned `true`. Code that relied on the key
surviving as null will now see it absent.

## `Toolkit::pythonApi()` moved to `laranail/python`

The HTTP client was only ever a third of the problem. `laranail/python` is a bidirectional bridge:
Laravel to Python over HTTP, Laravel to Python as a hardened local process, and Python back to Laravel
through HMAC-signed callbacks — with an allow-list of interpreters, payloads on stdin rather than
argv, an env allow-list for the child process, and a redactor that masks the literal secret values the
package injected.

```diff
+   composer require laranail/python
```

```diff
-   Toolkit::pythonApi()->fastapi()->post('/predict', $payload);
+   Python::service('fastapi')->post('/predict', $payload);
```

- `Toolkit::pythonApi()` and `Laranail::pythonApi()` are removed, along with
  `Services\PythonApiService`, its contract, `Services\PythonServiceDefinition` and
  `Exceptions\PythonApiException`.
- `config('laranail.toolkit.python.services.*')` becomes `config('laranail.python.services.*')`.
  **Env var names are unchanged**, so an existing `.env` keeps working. Run
  `php artisan laranail::python.install`.
- `fastapi()` and `flask()` are no longer hardcoded methods — `service('fastapi')` takes the name.
  That was the extraction blocker in the client implementation this generalises.

**One behaviour change.** `timeout` used to fall back to `laranail.toolkit.http.request_timeout`;
`laranail/python` has its own `defaults.timeout` and does not read toolkit's HTTP config.

## The captcha module moved to `laranail/captcha`

It outgrew a toolkit module. The replacement covers eleven providers rather than five, adds
environment-scoped credentials, a database-backed settings store, replay and hostname enforcement,
and an edge bot-management middleware — none of which belongs behind `Toolkit::captcha()`.

```diff
+   composer require laranail/captcha
```

- `Toolkit::captcha()` is removed. Use the `Captcha` facade, which `laranail/captcha` registers.
- `config('laranail.toolkit.captcha.*')` becomes `config('laranail.captcha.*')`, with per-provider
  credentials under an environment block. Run `php artisan laranail::captcha.install`.
- The `Captcha` alias is no longer registered here, so the two packages no longer collide.
