<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Support\Facades\Cache;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Throwable;

/**
 * Stores notifications in the cache under a (prefixed) key for later retrieval.
 */
class CacheChannel extends AbstractNotificationChannel
{
    public function getName(): string
    {
        return 'cache';
    }

    public function send(NotificationMessage $message): bool
    {
        $data = $message->toData();
        $key = $this->generateKey($data);
        $ttl = (int) ($this->config['ttl'] ?? 3600);

        $payload = [
            'message' => $message->body,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ];

        try {
            Cache::put($key, $payload, $ttl);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function generateKey(array $data): string
    {
        $prefix = (string) ($this->config['key_prefix'] ?? 'notification_');
        $id = isset($data['id']) ? (string) $data['id'] : uniqid();

        return $prefix . $id;
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'key_prefix' => 'notification_',
            'ttl' => 3600,
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return [];
    }
}
