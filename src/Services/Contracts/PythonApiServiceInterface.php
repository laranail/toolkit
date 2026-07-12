<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Illuminate\Http\Client\PendingRequest;

/**
 * Factory for pre-configured HTTP clients targeting named Python (FastAPI /
 * Flask / any) microservices.
 *
 * Services are declared under `config('laranail.toolkit.python.services.*')`
 * (base URL, timeout, retry, SSL/CA-cert, health contract). Resolve a client by
 * name with {@see service()} — `fastapi()`/`flask()` are convenience shims — and
 * probe liveness with {@see health()}.
 */
interface PythonApiServiceInterface
{
    /** Build a configured client for the named service. */
    public function service(string $name): PendingRequest;

    /** Convenience shim for `service('fastapi')`. */
    public function fastapi(): PendingRequest;

    /** Convenience shim for `service('flask')`. */
    public function flask(): PendingRequest;

    /** Run the named service's configured health check. */
    public function health(string $name): bool;

    /** Convenience shim for `health('fastapi')`. */
    public function fastapiHealth(): bool;

    /** Convenience shim for `health('flask')`. */
    public function flaskHealth(): bool;
}
