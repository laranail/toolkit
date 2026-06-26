<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Captcha\Providers\RecaptchaProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class RecaptchaProviderTest extends TestCase
{
    private function provider(float $minScore = 0.5): RecaptchaProvider
    {
        return new RecaptchaProvider('site-key', 'secret-key', $minScore);
    }

    public function test_successful_verification_hits_endpoint_with_secret_response_and_remoteip(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true], 200),
        ]);

        $result = $this->provider()->verify('token-123', [], '203.0.113.7');

        $this->assertTrue($result->isSuccess());
        $this->assertSame('recaptcha', $result->getProviderName());

        Http::assertSent(fn ($request) => $request->url() === 'https://www.google.com/recaptcha/api/siteverify'
            && $request['secret'] === 'secret-key'
            && $request['response'] === 'token-123'
            && $request['remoteip'] === '203.0.113.7');
    }

    public function test_remoteip_is_omitted_when_not_provided(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->provider()->verify('token-123');

        Http::assertSent(fn ($request) => !isset($request['remoteip']));
    }

    #[Group('security')]
    public function test_fails_closed_on_server_error(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response('boom', 500),
        ]);

        $result = $this->provider()->verify('token-123');

        $this->assertTrue($result->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timed out');
        });

        $result = $this->provider()->verify('token-123');

        $this->assertTrue($result->isFailure());
        $this->assertSame('Verification service unavailable', $result->getFirstError());
    }

    #[Group('security')]
    public function test_fails_closed_on_malformed_body(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response('not-json-at-all', 200),
        ]);

        $result = $this->provider()->verify('token-123');

        $this->assertTrue($result->isFailure());
    }

    #[Group('security')]
    public function test_fails_closed_when_not_configured(): void
    {
        $result = (new RecaptchaProvider('', ''))->verify('token-123');

        $this->assertTrue($result->isFailure());
    }

    public function test_v3_score_below_threshold_fails(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.2], 200),
        ]);

        $result = $this->provider(0.5)->verify('token-123');

        $this->assertTrue($result->isFailure());
        $this->assertSame('Score below threshold', $result->getFirstError());
    }

    public function test_v3_score_at_or_above_threshold_succeeds(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9], 200),
        ]);

        $result = $this->provider(0.5)->verify('token-123');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(0.9, $result->score());
    }

    public function test_v2_response_without_score_does_not_fail_on_action(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true], 200),
        ]);

        // Even with an expected action, a v2 response (no score) must not fail.
        $result = $this->provider()->verify('token-123', ['action' => 'login']);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1.0, $result->score());
    }

    public function test_v3_action_mismatch_fails(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'signup'], 200),
        ]);

        $result = $this->provider()->verify('token-123', ['action' => 'login']);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Action mismatch', $result->getFirstError());
    }

    public function test_v3_action_match_succeeds(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'login'], 200),
        ]);

        $result = $this->provider()->verify('token-123', ['action' => 'login']);

        $this->assertTrue($result->isSuccess());
    }

    public function test_provider_failure_surfaces_error_codes(): void
    {
        Http::fake([
            'www.google.com/*' => Http::response([
                'success' => false,
                'error-codes' => ['invalid-input-response'],
            ], 200),
        ]);

        $result = $this->provider()->verify('token-123');

        $this->assertTrue($result->isFailure());
        $this->assertSame(['invalid-input-response'], $result->errorCodes());
    }
}
