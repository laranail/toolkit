<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\GuardsOutboundUrls;
use Throwable;

/**
 * Sends messages via the Apple Business Messages gateway.
 *
 * Hardened over the legacy version: the gateway URL is SSRF-guarded, required
 * credentials are checked up front, and transport/non-2xx errors fail soft. The
 * bearer JWT is built locally and never logged.
 */
class AppleBusinessMessagesChannel extends AbstractNotificationChannel
{
    use GuardsOutboundUrls;

    public function getName(): string
    {
        return 'apple_business_messages';
    }

    public function send(NotificationMessage $message): bool
    {
        $businessId = $this->config['business_id'] ?? null;
        $apiKey = $this->config['api_key'] ?? null;
        $apiSecret = $this->config['api_secret'] ?? null;

        $recipientId = $message->option('recipient_id');

        if (empty($recipientId) || empty($businessId) || empty($apiKey) || empty($apiSecret)) {
            return false;
        }

        $url = (string) ($this->config['api_url'] ?? 'https://mspgw.apple.com/v1/message');

        if (!$this->isOutboundUrlAllowed($url)) {
            return false;
        }

        $payload = [
            'businessId' => $businessId,
            'conversationId' => $message->option('conversation_id', $recipientId),
            'message' => [
                'text' => $message->body,
                'locale' => (string) $message->option('locale', 'en'),
            ],
        ];

        if (!empty($message->option('suggestions'))) {
            $payload['message']['suggestions'] = $message->option('suggestions');
        }

        if (!empty($message->option('rich_link'))) {
            $payload['message']['richLink'] = $message->option('rich_link');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->generateJwt((string) $apiKey, (string) $apiSecret),
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Build a minimal HS256 JWT for the gateway.
     *
     * Mirrors the legacy helper; for production a vetted JWT library is advised.
     */
    private function generateJwt(string $apiKey, string $apiSecret): string
    {
        $header = (string) json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $claims = (string) json_encode([
            'iss' => $apiKey,
            'iat' => time(),
            'exp' => time() + 3600,
        ]);

        $encodedHeader = $this->base64UrlEncode($header);
        $encodedClaims = $this->base64UrlEncode($claims);

        $signature = hash_hmac('sha256', $encodedHeader . '.' . $encodedClaims, $apiSecret, true);

        return $encodedHeader . '.' . $encodedClaims . '.' . $this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $value): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($value));
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'api_url' => 'https://mspgw.apple.com/v1/message',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['business_id', 'api_key', 'api_secret'];
    }

    protected function performCustomValidation(): bool
    {
        $businessId = (string) ($this->config['business_id'] ?? '');

        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $businessId) === 1;
    }
}
