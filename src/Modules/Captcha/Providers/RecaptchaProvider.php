<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult;

/**
 * Google reCAPTCHA driver, supporting both v2 (no score) and v3 (score + action).
 *
 * Verification fails closed: connection errors, non-2xx responses, or malformed
 * bodies all produce a failed result instead of throwing.
 */
final class RecaptchaProvider implements CaptchaProviderInterface
{
    private const API_URL = 'https://www.google.com/recaptcha/api/siteverify';

    private const PROVIDER_NAME = 'recaptcha';

    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly float $minScore = 0.5,
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
                ['reCAPTCHA not properly configured'],
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
            Log::error('reCAPTCHA verification transport error', ['error' => $e->getMessage()]);

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
            Log::warning('reCAPTCHA verification failed', ['errors' => $errors]);

            return CaptchaVerificationResult::failure(self::PROVIDER_NAME, $errors);
        }

        $hasScore = array_key_exists('score', $data);
        $score = $hasScore ? (float) $data['score'] : 0.0;
        $action = isset($data['action']) ? (string) $data['action'] : '';

        // Action and score checks only apply to v3 (a response carrying a score).
        if ($hasScore) {
            $expectedAction = isset($options['action']) ? (string) $options['action'] : null;

            if ($expectedAction !== null && $expectedAction !== '' && $action !== $expectedAction) {
                return CaptchaVerificationResult::failure(
                    self::PROVIDER_NAME,
                    ['Action mismatch'],
                    ['expected' => $expectedAction, 'actual' => $action],
                );
            }

            if ($score < $this->minScore) {
                return CaptchaVerificationResult::failure(
                    self::PROVIDER_NAME,
                    ['Score below threshold'],
                    ['score' => $score, 'threshold' => $this->minScore],
                );
            }
        }

        return CaptchaVerificationResult::success(
            self::PROVIDER_NAME,
            $hasScore ? $score : 1.0,
            [
                'action' => $action,
                'challenge_ts' => $data['challenge_ts'] ?? null,
                'hostname' => $data['hostname'] ?? null,
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

    public function getMinScore(): float
    {
        return $this->minScore;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
