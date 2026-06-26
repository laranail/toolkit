<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLog;
use Simtabi\Laranail\Toolkit\Modules\Security\AccessLog\AccessLogMiddleware;
use Simtabi\Laranail\Toolkit\Modules\Security\SecurityData;
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

    public function test_deeply_nested_secrets_are_redacted_recursively(): void
    {
        $request = Request::create('/deep', 'POST', [
            'wrapper' => [
                'inner' => [
                    'password' => 'deep-secret',
                    'safe' => 'keep',
                ],
            ],
        ]);

        $this->logRequest($request);

        $log = AccessLog::firstOrFail();

        $this->assertSame('[REDACTED]', $log->request_data['wrapper']['inner']['password']);
        $this->assertSame('keep', $log->request_data['wrapper']['inner']['safe']);
    }

    public function test_default_deny_list_comes_from_security_data(): void
    {
        // The middleware's default keys derive from SecurityData::redactKeys();
        // every key it reports must therefore be redacted by the middleware.
        $keys = SecurityData::redactKeys();

        $this->assertNotSame([], $keys, 'SecurityData must publish a redaction deny-list');

        $payload = [];
        foreach ($keys as $key) {
            $payload[$key] = 'leak-' . $key;
        }
        $payload['public'] = 'visible';

        $this->logRequest(Request::create('/keys', 'POST', $payload));

        $log = AccessLog::firstOrFail();

        foreach ($keys as $key) {
            $this->assertSame('[REDACTED]', $log->request_data[$key], "key not redacted: {$key}");
        }
        $this->assertSame('visible', $log->request_data['public']);
    }

    public function test_config_override_replaces_the_default_deny_list(): void
    {
        config()->set('laranail-toolkit.access_log.redact', ['custom_field']);

        $request = Request::create('/override', 'POST', [
            'custom_field' => 'hide-me',
            'password' => 'no-longer-in-deny-list',
        ]);

        $this->logRequest($request);

        $log = AccessLog::firstOrFail();

        $this->assertSame('[REDACTED]', $log->request_data['custom_field']);
        // password is not in the overriding list, so it is stored verbatim.
        $this->assertSame('no-longer-in-deny-list', $log->request_data['password']);
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
        config()->set('laranail-toolkit.access_log.enabled', false);

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
