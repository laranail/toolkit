<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Notifications;

use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class NotificationMessageTest extends TestCase
{
    public function test_make_lifts_known_keys_onto_typed_fields(): void
    {
        $message = NotificationMessage::make('body', [
            'subject' => 'Subject',
            'to' => 'a@b.com',
            'level' => 'warning',
            'extra' => 'kept',
        ]);

        $this->assertSame('body', $message->body);
        $this->assertSame('Subject', $message->subject);
        $this->assertSame('a@b.com', $message->to);
        $this->assertSame('warning', $message->level);
        $this->assertSame('kept', $message->option('extra'));
    }

    public function test_to_array_round_trips_through_from_array(): void
    {
        $original = new NotificationMessage(
            body: 'hello',
            subject: 'Hi',
            to: ['a@b.com', 'c@d.com'],
            level: 'error',
            options: ['k' => 'v'],
        );

        $rebuilt = NotificationMessage::fromArray($original->toArray());

        $this->assertSame($original->toArray(), $rebuilt->toArray());
    }

    public function test_to_data_folds_typed_fields_back_into_the_bag(): void
    {
        $message = new NotificationMessage('body', subject: 'S', to: 'x', level: 'info', options: ['o' => 1]);

        $data = $message->toData();

        $this->assertSame('S', $data['subject']);
        $this->assertSame('x', $data['to']);
        $this->assertSame('info', $data['level']);
        $this->assertSame(1, $data['o']);
    }

    public function test_with_options_merges_immutably(): void
    {
        $message = new NotificationMessage('body', options: ['a' => 1]);

        $next = $message->withOptions(['b' => 2]);

        $this->assertSame(['a' => 1], $message->options);
        $this->assertSame(['a' => 1, 'b' => 2], $next->options);
    }
}
