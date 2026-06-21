<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\GuardsOutboundUrls;
use Throwable;

/**
 * Best-effort push channel (OneSignal-style HTTP API).
 *
 * Ported faithfully from the legacy OneSignal stub but hardened: the endpoint is
 * configurable and SSRF-guarded, required credentials are checked up front, and
 * transport/non-2xx errors fail soft without ever logging the API key.
 */
class PushChannel extends AbstractNotificationChannel
{
    use GuardsOutboundUrls;

    public function getName(): string
    {
        return 'push';
    }

    public function send(NotificationMessage $message): bool
    {
        $apiKey = $this->config['api_key'] ?? null;
        $appId = $this->config['app_id'] ?? null;

        if (empty($apiKey) || empty($appId)) {
            return false;
        }

        $url = (string) ($this->config['api_url'] ?? 'https://onesignal.com/api/v1/notifications');

        if (!$this->isOutboundUrlAllowed($url)) {
            return false;
        }

        $title = (string) $message->option('title', $this->config['default_title'] ?? 'Notification');

        /** @var array<int, string> $segments */
        $segments = is_array($message->option('segments')) ? $message->option('segments') : ['All'];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . (string) $apiKey,
            ])->post($url, [
                'app_id' => $appId,
                'headings' => ['en' => $title],
                'contents' => ['en' => $message->body],
                'included_segments' => $segments,
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
            'default_title' => 'Notification',
            'api_url' => 'https://onesignal.com/api/v1/notifications',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['api_key', 'app_id'];
    }
}
