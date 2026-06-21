<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult;

/**
 * Cloudflare Turnstile driver.
 *
 * Turnstile does not return a score, so a successful verification yields a
 * score of 1.0. Verification fails closed on transport/response errors.
 */
final class TurnstileProvider implements CaptchaProviderInterface
{
    private const API_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const PROVIDER_NAME = 'turnstile';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly int $timeout = 30,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function verify(string $token, array $options = [], ?string $remoteIp = null): CaptchaVerificationResult
    {
        if (!$this->isConfigured()) {
            return CaptchaVerificationResult::failure(
                self::PROVIDER_NAME,
                ['Turnstile not properly configured'],
            );
        }

        $payload = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];

        if ($remoteIp !== null) {
            $payload['remoteip'] = $remoteIp;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->asForm()
                ->post(self::API_URL, $payload);
        } catch (ConnectionException $e) {
            Log::error('Turnstile verification transport error', ['error' => $e->getMessage()]);

            return CaptchaVerificationResult::failure(
                self::PROVIDER_NAME,
                ['Verification service unavailable'],
            );
        }

        $data = $response->json();

        if (!$response->successful() || !is_array($data) || !isset($data['success'])) {
            return CaptchaVerificationResult::failure(
                self::PROVIDER_NAME,
                ['Invalid API response'],
                ['http_status' => $response->status()],
            );
        }

        if ($data['success'] !== true) {
            /** @var array<int, string> $errors */
            $errors = is_array($data['error-codes'] ?? null) ? $data['error-codes'] : ['Unknown error'];
            Log::warning('Turnstile verification failed', ['errors' => $errors]);

            return CaptchaVerificationResult::failure(self::PROVIDER_NAME, $errors);
        }

        return CaptchaVerificationResult::success(
            self::PROVIDER_NAME,
            1.0,
            [
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
                'action' => $data['action'] ?? null,
                'cdata' => $data['cdata'] ?? null,
            ],
        );
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function isConfigured(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
