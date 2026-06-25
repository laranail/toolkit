<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\FriendlyCaptchaProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FriendlyCaptchaProviderTest extends TestCase
{
    private function provider(bool $useEu = false): FriendlyCaptchaProvider
    {
        return new FriendlyCaptchaProvider('site-key', 'api-key', $useEu);
    }

    public function test_successful_verification_sends_api_key_header_and_solution_body(): void
    {
        Http::fake([
            'global.frcapi.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->provider()->verify('token-abc');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1.0, $result->score());
        $this->assertSame('friendly_captcha', $result->getProviderName());

        Http::assertSent(fn ($request) => $request->url() === 'https://global.frcapi.com/api/v2/captcha/siteverify'
            && $request->hasHeader('X-API-Key', 'api-key')
            && $request['solution'] === 'token-abc');
    }

    public function test_eu_endpoint_is_used_when_enabled(): void
    {
        Http::fake([
            'eu.frcapi.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->assertTrue($this->provider(useEu: true)->verify('token-abc')->isSuccess());

        Http::assertSent(fn ($request) => $request->url() === 'https://eu.frcapi.com/api/v2/captcha/siteverify');
    }

    public function test_unsuccessful_response_surfaces_errors(): void
    {
        Http::fake([
            'global.frcapi.com/*' => Http::response(['success' => false, 'errors' => ['solution_invalid']], 200),
        ]);

        $result = $this->provider()->verify('token-abc');

        $this->assertTrue($result->isFailure());
        $this->assertSame(['solution_invalid'], $result->errorCodes());
    }

    #[Group('security')]
    public function test_fails_closed_on_server_error(): void
    {
        Http::fake([
            'global.frcapi.com/*' => Http::response('boom', 500),
        ]);

        $this->assertTrue($this->provider()->verify('token-abc')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $this->assertTrue($this->provider()->verify('token-abc')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_on_malformed_body(): void
    {
        Http::fake([
            'global.frcapi.com/*' => Http::response('garbage', 200),
        ]);

        $this->assertTrue($this->provider()->verify('token-abc')->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_when_not_configured(): void
    {
        Http::fake();

        $provider = new FriendlyCaptchaProvider('', '');

        $this->assertFalse($provider->isConfigured());
        $this->assertTrue($provider->verify('token-abc')->isFailure());

        Http::assertNothingSent();
    }
}
