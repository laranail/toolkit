<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Toolkit\Services\HttpConfigurationService;

class HttpConfigurationServiceTest extends TestCase
{
    public function test_defaults_are_seeded_from_the_toolkit_http_config(): void
    {
        $service = new HttpConfigurationService($this->config([
            'laranail-toolkit.http.persist_connection' => false,
            'laranail-toolkit.http.request_timeout' => 30,
            'laranail-toolkit.http.max_retries' => 5,
            'laranail-toolkit.http.cache_ttl' => 7,
        ]));

        $this->assertFalse($service->isPersistConnection());
        $this->assertSame(30, $service->getRequestTimeout());
        $this->assertSame(5, $service->getMaxRetries());
        $this->assertSame(7, $service->getCacheTtl());
    }

    public function test_falls_back_to_sane_defaults_when_config_is_absent(): void
    {
        $service = new HttpConfigurationService($this->config([]));

        $this->assertTrue($service->isPersistConnection());
        $this->assertSame(60, $service->getRequestTimeout());
        $this->assertSame(10, $service->getMaxRetries());
        $this->assertSame(10, $service->getCacheTtl());
    }

    public function test_fluent_setters_override_and_build_the_guzzle_config(): void
    {
        $service = new HttpConfigurationService($this->config([]));

        $returned = $service
            ->setPersistConnection(false)
            ->setRequestTimeout(15)
            ->setMaxRetries(2)
            ->setCacheTtl(99);

        $this->assertSame($service, $returned);
        $this->assertSame([
            'persist' => false,
            'timeout' => 15,
            'retry' => ['max' => 2],
            'cache_ttl' => 99,
        ], $service->toGuzzleConfig());
    }

    /**
     * @param array<string, mixed> $items
     */
    private function config(array $items): Repository
    {
        return new Repository($items);
    }
}
