<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Contracts;

use Simtabi\Laranail\Toolkit\Modules\Captcha\Results\CaptchaVerificationResult;

/**
 * Contract for CAPTCHA verification drivers (reCAPTCHA, hCaptcha, Turnstile, ...).
 */
interface CaptchaProviderInterface
{
    /**
     * Verify a CAPTCHA token against the provider's verification endpoint.
     *
     * Implementations MUST fail closed: any transport error, non-2xx response,
     * or malformed body has to yield a failed {@see CaptchaVerificationResult}
     * rather than throwing or reporting success.
     *
     * @param string               $token    The CAPTCHA response token supplied by the client.
     * @param array<string, mixed> $options  Provider-specific options (e.g. expected `action`).
     * @param string|null          $remoteIp Optional client IP forwarded to the provider.
     */
    public function verify(string $token, array $options = [], ?string $remoteIp = null): CaptchaVerificationResult;

    /**
     * Get the canonical provider name (e.g. "recaptcha").
     */
    public function getName(): string;

    /**
     * Determine whether the provider has the credentials it needs to verify.
     */
    public function isConfigured(): bool;

    /**
     * Get the public site key used for frontend widget integration.
     */
    public function getSiteKey(): string;
}
