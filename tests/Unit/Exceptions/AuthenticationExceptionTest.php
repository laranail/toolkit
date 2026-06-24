<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\Toolkit\Exceptions\AuthenticationException;
use Simtabi\Laranail\Toolkit\Exceptions\LaranailException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class AuthenticationExceptionTest extends TestCase
{
    public function test_it_extends_the_rich_laranail_base(): void
    {
        $this->assertInstanceOf(LaranailException::class, AuthenticationException::missingGuard());
    }

    public function test_missing_guard_factory(): void
    {
        $exception = AuthenticationException::missingGuard();

        $this->assertSame(2001, $exception->getCode());
        $this->assertSame(401, $exception->getStatus());
        $this->assertSame('Authentication guard not specified.', $exception->getUserMessage());
        $this->assertSame([], $exception->getContext());
    }

    public function test_invalid_guard_factory_carries_context(): void
    {
        $exception = AuthenticationException::invalidGuard('admin');

        $this->assertSame(2002, $exception->getCode());
        $this->assertSame(['guard' => 'admin'], $exception->getContext());
        $this->assertSame(401, $exception->getStatus());
    }

    public function test_unauthenticated_factory_includes_guard_when_given(): void
    {
        $withGuard = AuthenticationException::unauthenticated('web');
        $this->assertSame(2003, $withGuard->getCode());
        $this->assertStringContainsString('web', $withGuard->getMessage());
        $this->assertSame(['guard' => 'web'], $withGuard->getContext());

        $withoutGuard = AuthenticationException::unauthenticated();
        $this->assertSame([], $withoutGuard->getContext());
        $this->assertStringNotContainsString('guard:', $withoutGuard->getMessage());
    }

    public function test_render_returns_json_envelope_for_json_requests(): void
    {
        config(['app.debug' => false]);

        $request = Request::create('/api/me', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = AuthenticationException::unauthenticated('web')->render($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Please log in to continue.', $payload['message']);
        $this->assertSame(['guard' => 'web'], $payload['errors']);
        $this->assertArrayNotHasKey('debug', $payload);
    }

    public function test_render_includes_debug_payload_when_debug_enabled(): void
    {
        config(['app.debug' => true]);

        $request = Request::create('/api/me', 'GET');
        $request->headers->set('Accept', 'application/json');

        $response = AuthenticationException::missingGuard()->render($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        /** @var array<string, mixed> $payload */
        $payload = $response->getData(true);
        $this->assertArrayHasKey('debug', $payload);
    }

    public function test_render_defers_to_default_for_non_json_requests(): void
    {
        $request = Request::create('/login', 'GET');

        $this->assertFalse(AuthenticationException::missingGuard()->render($request));
    }
}
