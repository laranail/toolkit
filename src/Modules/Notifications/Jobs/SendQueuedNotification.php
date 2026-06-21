<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;

/**
 * Queued notification delivery.
 *
 * Replaces the legacy `dispatch(Closure)` that captured the whole service
 * (including its HTTP/mailer clients) and was therefore fragile to serialize.
 * This job carries ONLY a JSON-safe payload — the serialized message array plus
 * the target channel names — and re-resolves a fresh {@see NotificationService}
 * from the container in {@see self::handle()}.
 */
class SendQueuedNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param array{body: string, subject: string|null, to: string|array<int, string>|null, level: string, options: array<string, mixed>} $message
     * @param array<int, string>                                                                                                          $channels
     */
    public function __construct(
        public readonly array $message,
        public readonly array $channels,
    ) {}

    /**
     * Rebuild the service and the message, then deliver synchronously.
     */
    public function handle(Container $container): void
    {
        $service = NotificationService::fromContainer($container);
        $message = NotificationMessage::fromArray($this->message);

        $service->dispatchNow($message, $this->channels);
    }
}
