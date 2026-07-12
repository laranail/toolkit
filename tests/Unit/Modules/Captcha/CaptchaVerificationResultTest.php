<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Captcha;

use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CaptchaVerificationResultTest extends TestCase
{
    public function test_success_named_constructor_populates_a_consistent_state(): void
    {
        $result = CaptchaVerificationResult::success('turnstile', 0.9, ['hostname' => 'example.com']);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isFailure());
        $this->assertSame('turnstile', $result->providerName());
        $this->assertSame('turnstile', $result->getProviderName());
        $this->assertSame(0.9, $result->score());
        $this->assertSame(0.9, $result->getScore());
        $this->assertSame([], $result->errorCodes());
        $this->assertSame([], $result->getErrors());
        $this->assertNull($result->getFirstError());
        $this->assertSame(['hostname' => 'example.com'], $result->context());
        $this->assertSame(['hostname' => 'example.com'], $result->getContext());
    }

    public function test_failure_named_constructor_forces_a_zero_score_and_carries_errors(): void
    {
        $result = CaptchaVerificationResult::failure(
            'hcaptcha',
            ['invalid-input-response', 'timeout-or-duplicate'],
            ['http_status' => 503],
        );

        $this->assertTrue($result->isFailure());
        $this->assertFalse($result->isSuccess());
        $this->assertSame('hcaptcha', $result->providerName());
        $this->assertSame('hcaptcha', $result->getProviderName());
        $this->assertSame(0.0, $result->score());
        $this->assertSame(0.0, $result->getScore());
        $this->assertSame(['invalid-input-response', 'timeout-or-duplicate'], $result->errorCodes());
        $this->assertSame(['invalid-input-response', 'timeout-or-duplicate'], $result->getErrors());
        $this->assertSame('invalid-input-response', $result->getFirstError());
        $this->assertSame(['http_status' => 503], $result->context());
        $this->assertSame(['http_status' => 503], $result->getContext());
    }

    public function test_get_first_error_is_null_when_there_are_no_errors(): void
    {
        $this->assertNull(CaptchaVerificationResult::failure('null')->getFirstError());
    }

    public function test_to_array_exposes_every_field_with_the_documented_keys(): void
    {
        $result = CaptchaVerificationResult::success('friendly_captcha', 1.0, ['challenge_ts' => '2026-01-01T00:00:00Z']);

        $this->assertSame([
            'success' => true,
            'provider' => 'friendly_captcha',
            'score' => 1.0,
            'errors' => [],
            'context' => ['challenge_ts' => '2026-01-01T00:00:00Z'],
        ], $result->toArray());
    }

    public function test_to_array_reflects_a_failed_result(): void
    {
        $result = CaptchaVerificationResult::failure('recaptcha', ['bad-request'], ['http_status' => 400]);

        $this->assertSame([
            'success' => false,
            'provider' => 'recaptcha',
            'score' => 0.0,
            'errors' => ['bad-request'],
            'context' => ['http_status' => 400],
        ], $result->toArray());
    }
}
