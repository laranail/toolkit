<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts\NotificationChannelInterface;

/**
 * Base class for notification channels.
 *
 * Owns the shared config-merge / enabled / validation plumbing; concrete
 * channels only declare their defaults, required keys, and the actual transport
 * in {@see NotificationChannelInterface::send()}.
 */
abstract class AbstractNotificationChannel implements NotificationChannelInterface
{
    /**
     * @var array<string, mixed>
     */
    protected array $config = [];

    protected bool $enabled = true;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->enabled = (bool) ($this->config['enabled'] ?? true);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function requiresConfig(): bool
    {
        return $this->getRequiredConfigKeys() !== [];
    }

    public function validateConfig(): bool
    {
        if (!$this->requiresConfig()) {
            return true;
        }

        foreach ($this->getRequiredConfigKeys() as $key) {
            if (empty($this->config[$key])) {
                return false;
            }
        }

        return $this->performCustomValidation();
    }

    /**
     * Default configuration merged under any supplied config.
     *
     * @return array<string, mixed>
     */
    abstract protected function getDefaultConfig(): array;

    /**
     * Configuration keys that must be present and non-empty to send.
     *
     * @return array<int, string>
     */
    abstract protected function getRequiredConfigKeys(): array;

    /**
     * Hook for channel-specific validation beyond required-key presence.
     */
    protected function performCustomValidation(): bool
    {
        return true;
    }
}
