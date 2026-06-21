<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\HcaptchaProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HcaptchaProviderTest extends TestCase
{
    private function provider(): HcaptchaProvider
    {
        return new HcaptchaProvider('site-key', 'secret-key');
    }

    public function test_successful_verification_hits_endpoint_with_secret_and_response(): void
    {
        Http::fake([
            'hcaptcha.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->provider()->verify('token-abc', [], '198.51.100.4');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1.0, $result->score());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://hcaptcha.com/siteverify'
                && $request['secret'] === 'secret-key'
                && $request['response'] === 'token-abc'
                && $request['remoteip'] === '198.51.100.4';
        });
    }

    #[Group('security')]
    public function test_fails_closed_on_server_error(): void
    {
        Http::fake([
            'hcaptcha.com/*' => Http::response('boom', 500),
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
            'hcaptcha.com/*' => Http::response('garbage', 200),
        ]);

        $this->assertTrue($this->provider()->verify('token-abc')->isFailure());
    }

    public function test_unsuccessful_response_surfaces_error_codes(): void
    {
        Http::fake([
            'hcaptcha.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']], 200),
        ]);

        $result = $this->provider()->verify('token-abc');

        $this->assertTrue($result->isFailure());
        $this->assertSame(['invalid-input-response'], $result->errorCodes());
    }
}
