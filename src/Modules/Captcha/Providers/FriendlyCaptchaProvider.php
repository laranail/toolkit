<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Captcha\Providers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\Captcha\CaptchaVerificationResult;

/**
 * Friendly Captcha driver (privacy-first, proof-of-work).
 *
 * Friendly Captcha does not return a score, so a successful verification yields
 * a score of 1.0. The verification request authenticates with the API key via
 * the `X-API-Key` header and posts the solution as JSON. An EU-resident
 * endpoint is selected when `useEuEndpoint` is set. Verification fails closed on
 * transport/response errors.
 */
final readonly class FriendlyCaptchaProvider implements CaptchaProviderInterface
{
    private const GLOBAL_API_URL = 'https://global.frcapi.com/api/v2/captcha/siteverify';

    private const EU_API_URL = 'https://eu.frcapi.com/api/v2/captcha/siteverify';

    private const PROVIDER_NAME = 'friendly_captcha';

    public function __construct(
        private string $siteKey,
        private string $apiKey,
        private bool $useEuEndpoint = false,
        private int $timeout = 30,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public function verify(string $token, array $options = [], ?string $remoteIp = null): CaptchaVerificationResult
    {
        if (!$this->isConfigured()) {
            return CaptchaVerificationResult::failure(
                self::PROVIDER_NAME,
                ['Friendly Captcha not properly configured'],
            );
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['X-API-Key' => $this->apiKey])
                ->asJson()
                ->post($this->apiUrl(), ['solution' => $token]);
        } catch (ConnectionException $e) {
            Log::error('Friendly Captcha verification transport error', ['error' => $e->getMessage()]);

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
            $errors = is_array($data['errors'] ?? null) ? $data['errors'] : ['Unknown error'];
            Log::warning('Friendly Captcha verification failed', ['errors' => $errors]);

            return CaptchaVerificationResult::failure(self::PROVIDER_NAME, $errors);
        }

        return CaptchaVerificationResult::success(
            self::PROVIDER_NAME,
            1.0,
            ['challenge_ts' => $data['challenge_ts'] ?? null],
        );
    }

    public function getName(): string
    {
        return self::PROVIDER_NAME;
    }

    public function isConfigured(): bool
    {
        return $this->siteKey !== '' && $this->apiKey !== '';
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }

    public function usesEuEndpoint(): bool
    {
        return $this->useEuEndpoint;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    private function apiUrl(): string
    {
        return $this->useEuEndpoint ? self::EU_API_URL : self::GLOBAL_API_URL;
    }
}
