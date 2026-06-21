<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\GuardsOutboundUrls;
use Throwable;

/**
 * Posts notifications to a Slack incoming webhook.
 *
 * Hardened over the legacy version: the `webhook_url` is null-checked before
 * use, the destination is SSRF-guarded, and transport/non-2xx errors fail soft
 * (boolean false) without leaking the webhook URL.
 */
class SlackChannel extends AbstractNotificationChannel
{
    use GuardsOutboundUrls;

    public function getName(): string
    {
        return 'slack';
    }

    public function send(NotificationMessage $message): bool
    {
        $webhookUrl = $this->config['webhook_url'] ?? null;

        if (!is_string($webhookUrl) || $webhookUrl === '') {
            return false;
        }

        if (!$this->isOutboundUrlAllowed($webhookUrl)) {
            return false;
        }

        $payload = [
            'text' => $message->body,
            'username' => (string) $message->option('username', $this->config['username'] ?? 'Notification Bot'),
            'icon_emoji' => (string) $message->option('icon', $this->config['icon'] ?? ':robot_face:'),
        ];

        if (!empty($message->option('channel'))) {
            $payload['channel'] = $message->option('channel');
        }

        if (!empty($message->option('attachments'))) {
            $payload['attachments'] = $message->option('attachments');
        }

        try {
            $response = Http::asJson()->post($webhookUrl, $payload);

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
            'username' => 'Notification Bot',
            'icon' => ':robot_face:',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['webhook_url'];
    }
}
