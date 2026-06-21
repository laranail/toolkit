<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerAwareTrait;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;

/**
 * Writes notifications to the application log at the message's severity level.
 */
class LogChannel extends AbstractNotificationChannel
{
    use LoggerAwareTrait;

    public function getName(): string
    {
        return 'log';
    }

    public function send(NotificationMessage $message): bool
    {
        $context = array_merge(['message' => $message->body], $message->toData());

        if ($this->logger !== null) {
            $this->logger->log($message->level, $message->body, $context);
        } else {
            Log::log($message->level, $message->body, $context);
        }

        return true;
    }

    protected function getDefaultConfig(): array
    {
        return ['enabled' => true];
    }

    protected function getRequiredConfigKeys(): array
    {
        return [];
    }
}
