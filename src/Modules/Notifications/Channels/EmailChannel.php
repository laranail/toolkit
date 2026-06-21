<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Channels;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Throwable;

/**
 * Sends notifications as plain-text email via Laravel's Mail facade.
 */
class EmailChannel extends AbstractNotificationChannel
{
    public function getName(): string
    {
        return 'email';
    }

    public function send(NotificationMessage $message): bool
    {
        $to = $message->to ?? $this->config['to'] ?? null;

        if (empty($to)) {
            return false;
        }

        $subject = $message->subject
            ?? (string) ($this->config['default_subject'] ?? 'Notification');
        $from = $message->option('from', $this->config['from'] ?? null);

        try {
            Mail::raw($message->body, static function (Message $mail) use ($to, $subject, $from): void {
                /** @var string|array<int, string> $to */
                $mail->to(is_array($to) ? $to : [$to])->subject($subject);

                if (!empty($from)) {
                    $mail->from((string) $from);
                }
            });

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => true,
            'default_subject' => 'System Notification',
        ];
    }

    protected function getRequiredConfigKeys(): array
    {
        return [];
    }
}
