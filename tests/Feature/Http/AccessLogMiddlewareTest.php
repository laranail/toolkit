<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLog;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class AccessLogMiddlewareTest extends TestCase
{
    private function logRequest(Request $request): void
    {
        $middleware = new AccessLogMiddleware();
        $middleware->handle($request, fn ($req) => new Response('ok'));
        $middleware->terminate($request, new Response('ok'));
    }

    public function test_sensitive_fields_are_redacted(): void
    {
        $request = Request::create('/login', 'POST', [
            'email' => 'user@example.com',
            'password' => 'super-secret',
            'token' => 'abc123',
            'profile' => ['api_key' => 'nested-secret', 'name' => 'Jane'],
        ]);

        $this->logRequest($request);

        $log = AccessLog::firstOrFail();

        $this->assertSame('[REDACTED]', $log->request_data['password']);
        $this->assertSame('[REDACTED]', $log->request_data['token']);
        $this->assertSame('[REDACTED]', $log->request_data['profile']['api_key']);
        $this->assertSame('user@example.com', $log->request_data['email']);
        $this->assertSame('Jane', $log->request_data['profile']['name']);
    }

    public function test_query_string_secrets_are_not_stored_in_url(): void
    {
        $request = Request::create('/callback?api_key=leak&page=2', 'GET');

        $this->logRequest($request);

        $log = AccessLog::firstOrFail();

        $this->assertStringNotContainsString('leak', $log->url);
        $this->assertStringNotContainsString('api_key', $log->url);
    }

    public function test_logging_can_be_disabled_via_config(): void
    {
        config()->set('laranail.toolkit.access_log.enabled', false);

        $this->logRequest(Request::create('/anything', 'GET'));

        $this->assertSame(0, AccessLog::count());
    }

    public function test_a_logging_failure_never_breaks_the_request(): void
    {
        // Drop the table so the insert fails; terminate() must swallow it.
        $this->app['db']->connection()->getSchemaBuilder()->drop('access_logs');

        $this->logRequest(Request::create('/anything', 'GET'));

        $this->assertTrue(true); // reached here = no exception propagated
    }
}
