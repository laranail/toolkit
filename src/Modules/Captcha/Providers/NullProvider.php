<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Providers;

use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult;

/**
 * Null CAPTCHA driver — a no-op fallback for development, testing, and
 * honeypot-style flows that gate on something other than a CAPTCHA challenge.
 *
 * It performs NO external verification and ALWAYS reports success with a score
 * of 1.0. It is always "configured".
 *
 * WARNING: this provider offers no bot protection. Do NOT use it in production
 * unless another control (e.g. rate-limiting, a honeypot field) actually guards
 * the request — otherwise every submission passes verification unchecked.
 */
final readonly class NullProvider implements CaptchaProviderInterface
{
    private const PROVIDER_NAME = 'null';

    public function __construct(
        private string $siteKey = 'null-site-key',
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function verify(string $token, array $options = [], ?string $remoteIp = null): CaptchaVerificationResult
    {
        return CaptchaVerificationResult::success(
            self::PROVIDER_NAME,
            1.0,
            ['note' => 'no external verification'],
        );
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }
}
