<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Exceptions\PythonApiException;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Services\Contracts\PythonApiServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;
use Throwable;

/**
 * Config-driven factory of HTTP clients for named Python microservices.
 *
 * Generalises the legacy FastAPI/Flask-hardcoded service into a registry keyed
 * on `config('laranail.toolkit.python.services.<name>')`: {@see service()}
 * builds a client for any named service through one {@see buildRequest()}
 * (base URL, timeout, retry, JSON, SSL/CA-cert verification), reusing
 * {@see HttpConfigurationServiceInterface} for timeout/retry defaults. The
 * `fastapi()`/`flask()` shims are named conveniences, and every health contract
 * (path + expected key/value) is configurable per service.
 */
final readonly class PythonApiService implements PythonApiServiceInterface
{
    public function __construct(
        private ConfigRepository $config,
        private HttpConfigurationServiceInterface $http,
        private LoggerInterface $logger,
    ) {}

    public function service(string $name): PendingRequest
    {
        return $this->buildRequest($name, $this->definition($name));
    }

    public function fastapi(): PendingRequest
    {
        return $this->service('fastapi');
    }

    public function flask(): PendingRequest
    {
        return $this->service('flask');
    }

    public function health(string $name): bool
    {
        $definition = $this->definition($name);

        try {
            $response = $this->buildRequest($name, $definition)->get($definition->healthPath);

            // Compare stringified so a non-string health value (bool/int) can
            // still match a configured `healthy_value`.
            return $response->successful()
                && Cast::toString($response->json($definition->healthKey)) === $definition->healthyValue;
        } catch (Throwable $e) {
            $this->logger->warning('Python API health check failed', [
                'service' => $name,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function fastapiHealth(): bool
    {
        return $this->health('fastapi');
    }

    public function flaskHealth(): bool
    {
        return $this->health('flask');
    }

    /**
     * Resolve a service's definition from config (timeout defaulting to the
     * shared HTTP config).
     *
     * @throws PythonApiException When the service is undefined or has no base URL.
     */
    private function definition(string $name): PythonServiceDefinition
    {
        $raw = $this->config->get("laranail.toolkit.python.services.{$name}");

        if (!is_array($raw)) {
            throw PythonApiException::unknownService($name);
        }

        $definition = PythonServiceDefinition::fromArray($raw, $this->http->getRequestTimeout());

        if ($definition->baseUrl === '') {
            throw PythonApiException::missingBaseUrl($name);
        }

        return $definition;
    }

    /**
     * Build the configured, JSON-aware client for a service definition,
     * applying SSL/CA-cert verification and warning on a misconfigured cert.
     */
    private function buildRequest(string $name, PythonServiceDefinition $definition): PendingRequest
    {
        $request = Http::baseUrl($definition->baseUrl)
            ->timeout($definition->timeout)
            ->retry($definition->retryTimes, $definition->retrySleepMs)
            ->acceptJson();

        if (!$definition->verifySsl) {
            return $request->withOptions(['verify' => false]);
        }

        if ($definition->caCert !== null) {
            if (is_file($definition->caCert)) {
                return $request->withOptions(['verify' => $definition->caCert]);
            }

            // Configured but absent: don't silently fall through — warn, then
            // defer to the system CA bundle (verify stays true).
            $this->logger->warning('Python API CA certificate configured but not found; using system CA bundle', [
                'service' => $name,
                'ca_cert' => $definition->caCert,
            ]);
        }

        return $request;
    }
}
