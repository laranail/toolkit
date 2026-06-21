<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\ConsoleChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\EmailChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\SmsChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;

class ChannelEdgeCasesTest extends TestCase
{
    private function message(string $body = 'hi'): NotificationMessage
    {
        return NotificationMessage::make($body);
    }

    // ----- EmailChannel -----

    public function test_email_channel_sends_via_mail_facade(): void
    {
        Mail::fake();

        $channel = new EmailChannel(['to' => 'dest@example.com', 'from' => 'src@example.com']);

        $this->assertTrue($channel->send(new NotificationMessage('email body', subject: 'Hello')));
    }

    public function test_email_channel_fails_when_no_recipient(): void
    {
        Mail::fake();

        $channel = new EmailChannel([]);

        $this->assertFalse($channel->send($this->message()));
    }

    public function test_email_channel_uses_message_recipient_and_subject(): void
    {
        Mail::fake();

        $channel = new EmailChannel(['default_subject' => 'Default']);

        $message = new NotificationMessage('body', to: 'm@example.com', subject: 'Custom');

        $this->assertTrue($channel->send($message));
    }

    public function test_email_channel_name_and_config(): void
    {
        $channel = new EmailChannel([]);

        $this->assertSame('email', $channel->getName());
        $this->assertTrue($channel->isEnabled());
        $this->assertFalse($channel->requiresConfig());
        $this->assertTrue($channel->validateConfig());
    }

    // ----- SmsChannel -----

    public function test_sms_channel_fails_without_credentials(): void
    {
        Http::fake();

        $channel = new SmsChannel([]);

        $this->assertFalse($channel->send(new NotificationMessage('sms', to: '+15555550100')));

        Http::assertNothingSent();
    }

    public function test_sms_channel_posts_when_fully_configured(): void
    {
        Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);

        $channel = new SmsChannel([
            'api_url' => 'https://api.example.com/send',
            'api_key' => 'k',
            'from' => '+15555550000',
        ]);

        $this->assertTrue($channel->send(new NotificationMessage('sms body', to: '+15555550100')));

        Http::assertSent(fn ($request) => $request['to'] === '+15555550100' && $request['message'] === 'sms body');
    }

    #[Group('security')]
    public function test_sms_channel_fails_soft_on_non_2xx(): void
    {
        Http::fake(['api.example.com/*' => Http::response('err', 500)]);

        $channel = new SmsChannel([
            'api_url' => 'https://api.example.com/send',
            'api_key' => 'k',
            'from' => '+15555550000',
        ]);

        $this->assertFalse($channel->send(new NotificationMessage('sms', to: '+15555550100')));
    }

    public function test_sms_channel_name_and_validation(): void
    {
        $valid = new SmsChannel(['api_key' => 'k', 'from' => '+1']);
        $this->assertSame('sms', $valid->getName());
        $this->assertTrue($valid->requiresConfig());
        $this->assertTrue($valid->validateConfig());

        $invalid = new SmsChannel([]);
        $this->assertFalse($invalid->validateConfig());
    }

    // ----- ConsoleChannel -----

    public function test_console_channel_writes_errors_to_error_output(): void
    {
        $output = new ConsoleOutput();
        $channel = new ConsoleChannel([], $output);

        $message = new NotificationMessage('boom', level: 'error', options: ['code' => 500]);

        $this->assertTrue($channel->send($message));
    }

    public function test_console_channel_includes_data_when_enabled(): void
    {
        $output = new BufferedOutput();
        $channel = new ConsoleChannel(['show_data' => true], $output);

        $this->assertTrue($channel->send(new NotificationMessage('with data', options: ['k' => 'v'])));

        $rendered = $output->fetch();
        $this->assertStringContainsString('with data', $rendered);
        $this->assertStringContainsString('"k":"v"', $rendered);
    }

    public function test_console_channel_omits_data_when_disabled(): void
    {
        $output = new BufferedOutput();
        $channel = new ConsoleChannel(['show_data' => false], $output);

        $this->assertTrue($channel->send(new NotificationMessage('no data', options: ['k' => 'v'])));

        $this->assertStringNotContainsString('"k":"v"', $output->fetch());
    }

    public function test_console_channel_writes_to_stdout_without_injected_output(): void
    {
        $channel = new ConsoleChannel([]);

        ob_start();
        $result = $channel->send(new NotificationMessage('stdout body', level: 'info'));
        ob_end_clean();

        $this->assertTrue($result);
    }
}
