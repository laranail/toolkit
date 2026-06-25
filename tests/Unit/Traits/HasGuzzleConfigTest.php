<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Traits;

use Simtabi\Laranail\Toolkit\Services\Contracts\HttpConfigurationServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Traits\HasGuzzleConfig;

/**
 * Test double exposing the protected {@see HasGuzzleConfig::httpConfig()}.
 */
class HasGuzzleConfigFixture
{
    use HasGuzzleConfig;

    public function config(): HttpConfigurationServiceInterface
    {
        return $this->httpConfig();
    }
}

class HasGuzzleConfigTest extends TestCase
{
    public function test_trait_resolves_the_http_configuration_service(): void
    {
        $object = new HasGuzzleConfigFixture();

        $this->assertInstanceOf(
            HttpConfigurationServiceInterface::class,
            $object->config(),
        );
    }

    public function test_resolved_service_builds_a_guzzle_config_array(): void
    {
        $config = new HasGuzzleConfigFixture()
            ->config()
            ->setRequestTimeout(10)
            ->setMaxRetries(2)
            ->toGuzzleConfig();

        $this->assertIsArray($config);
    }
}
