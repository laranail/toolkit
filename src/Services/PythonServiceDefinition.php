<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Simtabi\Laranail\Toolkit\Support\Cast;

/**
 * Immutable description of one named Python (or any external HTTP) service, as
 * read from `config('laranail.toolkit.python.services.<name>')`.
 *
 * Consumed by {@see PythonApiService} to build a configured HTTP client and to
 * run its health check — so base URL, timeout, retry, SSL/CA-cert and the
 * health contract (path + expected key/value) are all data, not hardcoded.
 */
final readonly class PythonServiceDefinition
{
    public function __construct(
        public string $baseUrl,
        public int $timeout,
        public bool $verifySsl = true,
        public ?string $caCert = null,
        public string $healthPath = '/health',
        public string $healthKey = 'status',
        public string $healthyValue = 'healthy',
        public int $retryTimes = 3,
        public int $retrySleepMs = 100,
    ) {}

    /**
     * Build from a config array, falling back to `$defaultTimeout` when the
     * service omits its own timeout.
     *
     * @param array<array-key, mixed> $config
     */
    public static function fromArray(array $config, int $defaultTimeout): self
    {
        $timeout = isset($config['timeout']) && is_numeric($config['timeout'])
            ? (int) $config['timeout']
            : $defaultTimeout;

        $caCert = $config['ca_cert'] ?? null;

        return new self(
            baseUrl: Cast::toString($config['base_url'] ?? ''),
            timeout: max(0, $timeout),
            verifySsl: Cast::toBool($config['verify_ssl'] ?? true, true),
            caCert: is_string($caCert) && $caCert !== '' ? $caCert : null,
            healthPath: Cast::toString($config['health_path'] ?? '/health', '/health'),
            healthKey: Cast::toString($config['health_key'] ?? 'status', 'status'),
            healthyValue: Cast::toString($config['healthy_value'] ?? 'healthy', 'healthy'),
            retryTimes: max(0, Cast::toInt($config['retry_times'] ?? 3, 3)),
            retrySleepMs: max(0, Cast::toInt($config['retry_sleep_ms'] ?? 100, 100)),
        );
    }
}
