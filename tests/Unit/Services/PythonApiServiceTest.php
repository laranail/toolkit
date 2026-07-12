<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Http;
use Psr\Log\AbstractLogger;
use Simtabi\Laranail\Toolkit\Exceptions\PythonApiException;
use Simtabi\Laranail\Toolkit\Services\Contracts\PythonApiServiceInterface;
use Simtabi\Laranail\Toolkit\Services\HttpConfigurationService;
use Simtabi\Laranail\Toolkit\Services\PythonApiService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class PythonApiServiceTest extends TestCase
{
    public function test_resolves_from_the_container_by_contract(): void
    {
        $this->assertInstanceOf(PythonApiService::class, $this->app->make(PythonApiServiceInterface::class));
    }

    public function test_service_targets_the_configured_base_url(): void
    {
        Http::fake();
        config()->set('laranail.toolkit.python.services.demo', ['base_url' => 'http://demo.test']);

        $this->app->make(PythonApiServiceInterface::class)->service('demo')->get('/ping');

        Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), 'http://demo.test'));
    }

    public function test_fastapi_shim_uses_the_fastapi_service(): void
    {
        Http::fake();
        config()->set('laranail.toolkit.python.services.fastapi.base_url', 'http://fastapi.test');

        $this->app->make(PythonApiServiceInterface::class)->fastapi()->get('/x');

        Http::assertSent(fn ($request): bool => str_starts_with((string) $request->url(), 'http://fastapi.test'));
    }

    public function test_health_is_true_for_the_configured_key_and_value(): void
    {
        config()->set('laranail.toolkit.python.services.demo', [
            'base_url' => 'http://demo.test',
            'health_path' => '/health',
            'health_key' => 'status',
            'healthy_value' => 'healthy',
        ]);
        Http::fake(['*' => Http::response(['status' => 'healthy'], 200)]);

        $this->assertTrue($this->app->make(PythonApiServiceInterface::class)->health('demo'));
    }

    public function test_health_is_false_when_the_value_does_not_match(): void
    {
        config()->set('laranail.toolkit.python.services.demo', [
            'base_url' => 'http://demo.test',
            'healthy_value' => 'healthy',
        ]);
        Http::fake(['*' => Http::response(['status' => 'down'], 200)]);

        $this->assertFalse($this->app->make(PythonApiServiceInterface::class)->health('demo'));
    }

    public function test_health_is_false_on_transport_failure(): void
    {
        config()->set('laranail.toolkit.python.services.demo', ['base_url' => 'http://demo.test']);
        Http::fake(fn () => throw new \RuntimeException('connection refused'));

        $this->assertFalse($this->app->make(PythonApiServiceInterface::class)->health('demo'));
    }

    public function test_unknown_service_throws(): void
    {
        $this->expectException(PythonApiException::class);

        $this->app->make(PythonApiServiceInterface::class)->service('does-not-exist');
    }

    public function test_missing_ca_cert_warns_instead_of_silently_falling_through(): void
    {
        $logger = new CollectingWarnLogger();

        $service = new PythonApiService(
            new Repository(['laranail' => ['toolkit' => ['python' => ['services' => [
                'demo' => ['base_url' => 'http://demo.test', 'verify_ssl' => true, 'ca_cert' => '/no/such/cert.pem'],
            ]]]]]),
            new HttpConfigurationService(new Repository([])),
            $logger,
        );

        $service->service('demo');

        $this->assertNotEmpty($logger->warnings);
    }
}

/**
 * Minimal PSR-3 logger recording `warning`-level messages.
 */
class CollectingWarnLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $warnings = [];

    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param array<mixed>       $context
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level === 'warning') {
            $this->warnings[] = (string) $message;
        }
    }
}
