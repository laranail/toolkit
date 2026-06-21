<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Support\Facades\File;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Throwable;

/**
 * Appends notifications as JSON lines to a file, with optional size rotation.
 */
class FileChannel extends AbstractNotificationChannel
{
    public function getName(): string
    {
        return 'file';
    }

    public function send(NotificationMessage $message): bool
    {
        $path = (string) ($this->config['path'] ?? '');

        if ($path === '') {
            return false;
        }

        $entry = [
            'timestamp' => now()->toISOString(),
            'message' => $message->body,
            'data' => $message->toData(),
        ];

        $line = json_encode($entry) . "\n";

        try {
            if ($this->config['rotation'] ?? false) {
                $this->rotateFileIfNeeded($path);
            }

            File::append($path, $line);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function rotateFileIfNeeded(string $path): void
    {
        $maxSize = (int) ($this->config['max_size'] ?? 10485760);

        if (is_file($path) && filesize($path) > $maxSize) {
            rename($path, $path . '.' . date('Y-m-d-H-i-s'));
        }
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'rotation' => false,
            'max_size' => 10485760,
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return ['path'];
    }
}
