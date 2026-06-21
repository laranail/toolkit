<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\GuardsOutboundUrls;
use Throwable;

/**
 * Delivers notifications to an arbitrary configured webhook endpoint.
 *
 * Hardened over the legacy version: the destination URL is SSRF-guarded (so a
 * misconfigured/attacker-influenced `url` cannot target internal hosts), the
 * HTTP method is restricted to a known verb set, and transport/non-2xx errors
 * fail soft without leaking the URL or any configured headers.
 */
class WebhookChannel extends AbstractNotificationChannel
{
    use GuardsOutboundUrls;

    private const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    public function getName(): string
    {
        return 'webhook';
    }

    public function send(NotificationMessage $message): bool
    {
        $url = $this->config['url'] ?? null;

        if (!is_string($url) || $url === '') {
            return false;
        }

        if (!$this->isOutboundUrlAllowed($url)) {
            return false;
        }

        /** @var array<string, string> $headers */
        $headers = is_array($this->config['headers'] ?? null) ? $this->config['headers'] : [];
        $method = strtoupper((string) ($this->config['method'] ?? 'POST'));

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            $method = 'POST';
        }

        $payload = array_merge([
            'message' => $message->body,
            'timestamp' => now()->toISOString(),
        ], $message->toData());

        try {
            $request = Http::withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url, $payload),
                'PUT' => $request->put($url, $payload),
                'PATCH' => $request->patch($url, $payload),
                'DELETE' => $request->delete($url, $payload),
                default => $request->post($url, $payload),
            };

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
            'method' => 'POST',
            'headers' => [],
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['url'];
    }
}
