<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\GuardsOutboundUrls;
use Throwable;

/**
 * Posts notifications to a Discord webhook.
 *
 * Hardened over the legacy version: `webhook_url` is null-checked, the
 * destination is SSRF-guarded, and transport/non-2xx errors fail soft without
 * leaking the webhook URL.
 */
class DiscordChannel extends AbstractNotificationChannel
{
    use GuardsOutboundUrls;

    public function getName(): string
    {
        return 'discord';
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
            'content' => $message->body,
            'username' => (string) $message->option('username', $this->config['username'] ?? 'Notification Bot'),
        ];

        if (!empty($message->option('embeds'))) {
            $payload['embeds'] = $message->option('embeds');
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
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['webhook_url'];
    }
}
