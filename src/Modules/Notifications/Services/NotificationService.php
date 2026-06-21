<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Services;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts\NotificationChannelInterface;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Enums\NotificationChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Jobs\SendQueuedNotification;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Support\NotificationResult;
use Throwable;

/**
 * Orchestrates delivery of a {@see NotificationMessage} across one or more
 * channels.
 *
 * Security/correctness invariants enforced here:
 *  - Channels can only ever be resolved from a fixed allow-list (the
 *    {@see NotificationChannel} enum) — never `new $classFromConfig`.
 *  - The `$channels` selector is strictly typed (`string|array|null`); an
 *    unexpected value is rejected rather than silently falling through to
 *    "all channels".
 *  - Queueing dispatches a serializable {@see SendQueuedNotification} job that
 *    carries a scalar/array payload, not a closure capturing this service.
 */
class NotificationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Registered channel instances, keyed by name.
     *
     * @var array<string, NotificationChannelInterface>
     */
    private array $channels = [];

    /**
     * @var array<int, string>
     */
    private array $defaultChannels = [];

    private bool $queueable;

    private ?string $queueConnection;

    private string $queueName;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->queueable = (bool) ($config['queueable'] ?? false);
        $this->queueConnection = isset($config['queue_connection']) ? (string) $config['queue_connection'] : null;
        $this->queueName = (string) ($config['queue_name'] ?? 'notifications');

        /** @var array<string, array<string, mixed>> $channelsConfig */
        $channelsConfig = is_array($config['channels'] ?? null) ? $config['channels'] : [];

        $this->registerDefaultChannels($channelsConfig);
    }

    /**
     * Register (or override) a channel instance under a name.
     *
     * The name must belong to the allow-list, so arbitrary keys cannot smuggle
     * in unexpected channels.
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): self
    {
        $this->assertAllowedChannel($name);

        $this->channels[$name] = $channel;

        return $this;
    }

    public function unregisterChannel(string $name): self
    {
        unset($this->channels[$name]);

        return $this;
    }

    public function getChannel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * @return array<string, NotificationChannelInterface>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * @param array<int, string> $channels
     */
    public function setDefaultChannels(array $channels): self
    {
        foreach ($channels as $name) {
            $this->assertAllowedChannel($name);
        }

        $this->defaultChannels = array_values($channels);

        return $this;
    }

    /**
     * Send a notification.
     *
     * @param NotificationMessage|string     $message  Typed payload, or a raw body string for convenience.
     * @param array<string, mixed>           $data     Legacy data bag merged into the message when $message is a string.
     * @param string|array<int, string>|null $channels Channel name, list of names, or null for the defaults.
     */
    public function send(
        NotificationMessage|string $message,
        array $data = [],
        string|array|null $channels = null,
        string $level = 'info',
    ): NotificationResult {
        $message = $message instanceof NotificationMessage
            ? $message
            : NotificationMessage::make($message, $data, $level);

        $channelNames = $this->resolveChannels($channels);

        if ($this->shouldQueue($message)) {
            return $this->queueNotification($message, $channelNames);
        }

        return $this->dispatchNow($message, $channelNames);
    }

    /**
     * Deliver immediately to the resolved channels (used by the queued job too).
     *
     * @param array<int, string> $channelNames
     */
    public function dispatchNow(NotificationMessage $message, array $channelNames): NotificationResult
    {
        $message = $message->withOptions(['level' => $message->level]);

        /** @var array<string, bool> $results */
        $results = [];
        /** @var array<string, string> $errors */
        $errors = [];

        foreach ($channelNames as $channelName) {
            $channel = $this->getChannel($channelName);

            if ($channel === null) {
                $this->logError("Channel '{$channelName}' not registered");
                $errors[$channelName] = 'Channel not registered';

                continue;
            }

            if (!$channel->isEnabled()) {
                $this->logInfo("Channel '{$channelName}' is disabled");
                $results[$channelName] = false;

                continue;
            }

            if ($channel->requiresConfig() && !$channel->validateConfig()) {
                $this->logWarning("Channel '{$channelName}' has invalid configuration");
                $errors[$channelName] = 'Invalid configuration';
                $results[$channelName] = false;

                continue;
            }

            try {
                $delivered = $channel->send($message);
                $results[$channelName] = $delivered;

                if ($delivered) {
                    $this->logInfo("Notification sent via '{$channelName}'");
                } else {
                    $this->logWarning("Failed to send notification via '{$channelName}'");
                }
            } catch (Throwable $e) {
                $this->logError("Exception in channel '{$channelName}': " . $e->getMessage());
                $errors[$channelName] = $e->getMessage();
                $results[$channelName] = false;
            }
        }

        return new NotificationResult($results, $errors);
    }

    /**
     * Send to every registered channel.
     *
     * @param array<string, mixed> $data
     */
    public function broadcast(NotificationMessage|string $message, array $data = [], string $level = 'info'): NotificationResult
    {
        return $this->send($message, $data, array_keys($this->channels), $level);
    }

    /**
     * @param array<string, mixed> $channelsConfig
     */
    private function registerDefaultChannels(array $channelsConfig): void
    {
        foreach ($channelsConfig as $name => $config) {
            $channelConfig = is_array($config) ? $config : [];
            $channel = $this->createChannelFromConfig((string) $name, $channelConfig);

            if ($channel === null) {
                continue;
            }

            $this->channels[(string) $name] = $channel;

            if ($channelConfig['default'] ?? false) {
                $this->defaultChannels[] = (string) $name;
            }
        }
    }

    /**
     * Build a channel instance strictly from the allow-list.
     *
     * Unlike the legacy version, a `class` key in config can NEVER be used to
     * instantiate an arbitrary class — only the enum's mapped implementations
     * are ever constructed.
     *
     * @param array<string, mixed> $config
     */
    private function createChannelFromConfig(string $name, array $config): ?NotificationChannelInterface
    {
        $channel = NotificationChannel::tryFromKey($name);

        if ($channel === null) {
            return null;
        }

        $class = $channel->channelClass();

        return new $class($config);
    }

    /**
     * Resolve the selector into a concrete list of channel names.
     *
     * @param string|array<int, string>|null $channels
     *
     * @return array<int, string>
     */
    private function resolveChannels(string|array|null $channels): array
    {
        if (is_string($channels)) {
            return [$channels];
        }

        if (is_array($channels)) {
            return array_values(array_map(static fn ($name): string => (string) $name, $channels));
        }

        if ($this->defaultChannels !== []) {
            return $this->defaultChannels;
        }

        return array_keys($this->channels);
    }

    private function shouldQueue(NotificationMessage $message): bool
    {
        if (!$this->queueable || $message->option('queued', false)) {
            return false;
        }

        return !$message->option('sync', false);
    }

    /**
     * Dispatch a serializable job rather than a closure capturing this service.
     *
     * @param array<int, string> $channelNames
     */
    private function queueNotification(NotificationMessage $message, array $channelNames): NotificationResult
    {
        // Mark as queued so the job's synchronous send does not re-queue.
        $queuedMessage = $message->withOptions(['queued' => true]);

        SendQueuedNotification::dispatch($queuedMessage->toArray(), $channelNames)
            ->onConnection($this->queueConnection)
            ->onQueue($this->queueName);

        return new NotificationResult(['queued' => true]);
    }

    /**
     * Resolve a configured {@see NotificationService} from the container.
     *
     * Used by {@see SendQueuedNotification} so the queued job never serializes a
     * live service (with its HTTP/mailer clients) — it rebuilds it on the worker.
     */
    public static function fromContainer(Container $container): self
    {
        return $container->make(self::class);
    }

    private function assertAllowedChannel(string $name): void
    {
        if (NotificationChannel::tryFromKey($name) === null) {
            throw new InvalidArgumentException("Unknown notification channel '{$name}'.");
        }
    }

    private function logInfo(string $message): void
    {
        $this->logger?->info($message);
    }

    private function logWarning(string $message): void
    {
        $this->logger?->warning($message);
    }

    private function logError(string $message): void
    {
        $this->logger?->error($message);
    }
}
