<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Throwable;

/**
 * Best-effort SMS channel.
 *
 * The legacy implementation was a stub pointing at a generic provider HTTP API
 * with no concrete delivery integration. It is ported faithfully — it POSTs to
 * the configured `api_url` when credentials are present — but made safe: it
 * never throws out to the caller and never logs the bearer token. Treat it as a
 * best-effort adapter; wire a real provider via `api_url`/`api_key` in config.
 */
class SmsChannel extends AbstractNotificationChannel
{
    public function getName(): string
    {
        return 'sms';
    }

    public function send(NotificationMessage $message): bool
    {
        $to = $message->to ?? $this->config['default_to'] ?? null;
        $from = $this->config['from'] ?? null;
        $apiKey = $this->config['api_key'] ?? null;
        $apiUrl = (string) ($this->config['api_url'] ?? '');

        if (empty($to) || empty($from) || empty($apiKey) || $apiUrl === '') {
            return false;
        }

        try {
            $response = Http::withToken((string) $apiKey)->post($apiUrl, [
                'from' => $from,
                'to' => $to,
                'message' => $message->body,
            ]);

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        } catch (Throwable) {
            return false;
        }
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'api_url' => 'https://api.sms-provider.com/send',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'from'];
    }
}
