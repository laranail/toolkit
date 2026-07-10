# Python API

`PythonApiService` is a config-driven factory of HTTP clients for named Python
(FastAPI / Flask / any) microservices — typically reached through a local reverse
proxy with an mkcert CA. Resolve it by contract
`Services\Contracts\PythonApiServiceInterface` (preferred) or via
`Toolkit::pythonApi()`.

```php
use Simtabi\Laranail\Toolkit\Facades\Toolkit;
use Simtabi\Laranail\Toolkit\Services\Contracts\PythonApiServiceInterface;

$response = Toolkit::pythonApi()->service('fastapi')->post('/embed', ['text' => $t]);
```

Services are declared under `config('laranail.toolkit.python.services.*')`; each
entry becomes a configured Laravel HTTP client (base URL, timeout, retry, JSON,
SSL/CA-cert verification). Timeout defaults to the shared
[`HttpConfigurationService`](configuration.md#laranailtoolkithttp) when a service
omits it.

## Methods

| Method | Returns | |
|--------|---------|---|
| `service(string $name)` | `PendingRequest` | Configured client for the named service. |
| `fastapi()` / `flask()` | `PendingRequest` | Shims for `service('fastapi'\|'flask')`. |
| `health(string $name)` | `bool` | Run the service's configured health check. |
| `fastapiHealth()` / `flaskHealth()` | `bool` | Health-check shims. |

`service()` returns Laravel's own fluent `PendingRequest`, so chain the request
from there. An unknown service (or one with no `base_url`) throws
`Exceptions\PythonApiException`.

```php
$api = Toolkit::pythonApi();

if ($api->fastapiHealth()) {
    $data = $api->fastapi()->get('/predict', ['q' => $q])->json();
}

// Add your own service in config, then:
$api->service('scraper')->timeout(120)->post('/run', $payload);
```

## SSL / CA certificates

- `verify_ssl = false` disables TLS verification entirely.
- `verify_ssl = true` with a `ca_cert` path verifies against that bundle (e.g. a
  mkcert root).
- If `verify_ssl` is true and `ca_cert` is **set but the file is missing**, the
  service logs a warning and defers to the system CA bundle rather than silently
  falling through.

## Health checks

The health contract is per-service data, not hardcoded: `health_path` (default
`/health`) is requested and the JSON `health_key` (default `status`) is compared to
`healthy_value` (default `healthy`). A transport failure returns `false` (logged).

## Configuration

Service definitions live under `laranail.toolkit.python.services` — see
[configuration](configuration.md#laranailtoolkitpython). The package ships
`fastapi` and `flask` pre-defined.

---

[← Docs index](../README.md#documentation)
