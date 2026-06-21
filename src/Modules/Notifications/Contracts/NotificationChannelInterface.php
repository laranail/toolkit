<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts;

use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;

/**
 * Contract for a single notification delivery channel (log, email, slack, ...).
 *
 * Implementations MUST fail soft: any transport/config error has to be reported
 * via the boolean return value rather than thrown out to the caller, and secrets
 * (webhook URLs, API keys, tokens) must never be logged or surfaced.
 */
interface NotificationChannelInterface
{
    /**
     * Send a notification through this channel.
     *
     * @return bool True when the channel accepted/delivered the message.
     */
    public function send(NotificationMessage $message): bool;

    /**
     * Determine whether the channel is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Validate the channel's configuration.
     */
    public function validateConfig(): bool;

    /**
     * Get the canonical channel identifier (e.g. "slack").
     */
    public function getName(): string;

    /**
     * Replace the channel configuration.
     *
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void;

    /**
     * Determine whether the channel requires configuration before it can send.
     */
    public function requiresConfig(): bool;
}
