<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Fluent builder for Guzzle/HTTP client configuration.
 *
 * Defaults are seeded once from `config('laranail-toolkit.http.*')` (each key
 * backed by an env override defined in `config/toolkit.php`); the fluent
 * setters then let a caller tweak a single value before building the array.
 */
class HttpConfigurationService implements HttpConfigurationServiceInterface
{
    private bool $persistConnection;

    private int $requestTimeout;

    private int $maxRetries;

    private int $cacheTtl;

    public function __construct(ConfigRepository $config)
    {
        $this->persistConnection = Cast::toBool($config->get('laranail-toolkit.http.persist_connection', true), true);
        $this->requestTimeout = Cast::toInt($config->get('laranail-toolkit.http.request_timeout', 60), 60);
        $this->maxRetries = Cast::toInt($config->get('laranail-toolkit.http.max_retries', 10), 10);
        $this->cacheTtl = Cast::toInt($config->get('laranail-toolkit.http.cache_ttl', 10), 10);
    }

    public function setPersistConnection(bool $persist): self
    {
        $this->persistConnection = $persist;

        return $this;
    }

    public function isPersistConnection(): bool
    {
        return $this->persistConnection;
    }

    public function setRequestTimeout(int $timeout): self
    {
        $this->requestTimeout = $timeout;

        return $this;
    }

    public function getRequestTimeout(): int
    {
        return $this->requestTimeout;
    }

    public function setMaxRetries(int $retries): self
    {
        $this->maxRetries = $retries;

        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setCacheTtl(int $ttl): self
    {
        $this->cacheTtl = $ttl;

        return $this;
    }

    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    public function toGuzzleConfig(): array
    {
        return [
            'persist' => $this->isPersistConnection(),
            'timeout' => $this->getRequestTimeout(),
            'retry' => ['max' => $this->getMaxRetries()],
            'cache_ttl' => $this->getCacheTtl(),
        ];
    }
}
