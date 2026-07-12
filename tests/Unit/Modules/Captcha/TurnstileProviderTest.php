<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\TurnstileProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class TurnstileProviderTest extends TestCase
{
    private function provider(): TurnstileProvider
    {
        return new TurnstileProvider('site-key', 'secret-key');
    }

    public function test_successful_verification_hits_endpoint_with_secret_and_response(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->provider()->verify('token-xyz', [], '192.0.2.10');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1.0, $result->score());

        Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'secret-key'
            && $request['response'] === 'token-xyz'
            && $request['remoteip'] === '192.0.2.10');
    }

    #[Group('security')]
    public function test_fails_closed_on_server_error(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('boom', 503),
        ]);

        $this->assertTrue($this->provider()->verify('token-xyz')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->assertTrue($this->provider()->verify('token-xyz')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_on_malformed_body(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response('garbage', 200),
        ]);

        $this->assertTrue($this->provider()->verify('token-xyz')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_when_not_configured(): void
    {
        Http::fake();

        $provider = new TurnstileProvider('', '');

        $this->assertFalse($provider->isConfigured());

        $result = $provider->verify('token-xyz');

        $this->assertTrue($result->isFailure());
        $this->assertSame(['Turnstile not properly configured'], $result->errorCodes());

        Http::assertNothingSent();
    }

    public function test_unsuccessful_response_surfaces_error_codes(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(
                ['success' => false, 'error-codes' => ['invalid-input-response']],
                200,
            ),
        ]);

        $result = $this->provider()->verify('token-xyz');

        $this->assertTrue($result->isFailure());
        $this->assertSame(['invalid-input-response'], $result->errorCodes());
    }

    public function test_unsuccessful_response_without_error_codes_falls_back_to_unknown(): void
    {
        Http::fake([
            'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
        ]);

        $this->assertSame(['Unknown error'], $this->provider()->verify('token-xyz')->errorCodes());
    }

    public function test_exposes_its_metadata(): void
    {
        $provider = new TurnstileProvider('site-key', 'secret-key', 12);

        $this->assertSame('turnstile', $provider->getName());
        $this->assertSame('site-key', $provider->getSiteKey());
        $this->assertSame(12, $provider->getTimeout());
    }
}
