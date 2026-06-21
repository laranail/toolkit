<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Notifications;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\AppleBusinessMessagesChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\DiscordChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\PushChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\SlackChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\WebhookChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class OutboundHttpChannelsTest extends TestCase
{
    private function message(string $body = 'hello'): NotificationMessage
    {
        return NotificationMessage::make($body);
    }

    public function test_webhook_success_returns_true_and_sends_expected_shape(): void
    {
        Http::fake([
            'example.com/*' => Http::response(['ok' => true], 200),
        ]);

        $channel = new WebhookChannel([
            'url' => 'https://example.com/hook',
            'headers' => ['X-Token' => 'abc'],
        ]);

        $this->assertTrue($channel->send($this->message('payload body')));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && $request->method() === 'POST'
                && $request['message'] === 'payload body';
        });
    }

    #[Group('security')]
    public function test_webhook_non_2xx_fails_soft(): void
    {
        Http::fake([
            'example.com/*' => Http::response('nope', 500),
        ]);

        $channel = new WebhookChannel(['url' => 'https://example.com/hook']);

        $this->assertFalse($channel->send($this->message()));
    }

    #[Group('security')]
    public function test_webhook_connection_exception_fails_soft(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('timed out');
        });

        $channel = new WebhookChannel(['url' => 'https://example.com/hook']);

        $this->assertFalse($channel->send($this->message()));
    }

    public function test_slack_success(): void
    {
        Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

        $channel = new SlackChannel(['webhook_url' => 'https://hooks.slack.com/services/T/B/X']);

        $this->assertTrue($channel->send($this->message('slack msg')));

        Http::assertSent(fn ($request) => $request['text'] === 'slack msg');
    }

    #[Group('security')]
    public function test_slack_missing_webhook_url_returns_failure_without_http(): void
    {
        Http::fake();

        $channel = new SlackChannel([]);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    public function test_discord_success(): void
    {
        Http::fake(['discord.com/*' => Http::response('', 204)]);

        $channel = new DiscordChannel(['webhook_url' => 'https://discord.com/api/webhooks/1/abc']);

        $this->assertTrue($channel->send($this->message('discord msg')));

        Http::assertSent(fn ($request) => $request['content'] === 'discord msg');
    }

    public function test_push_requires_credentials(): void
    {
        Http::fake();

        $channel = new PushChannel(['api_key' => '', 'app_id' => '']);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    public function test_push_success_does_not_leak_api_key_in_body(): void
    {
        Http::fake(['onesignal.com/*' => Http::response(['id' => 'x'], 200)]);

        $channel = new PushChannel(['api_key' => 'super-secret', 'app_id' => 'app-1']);

        $this->assertTrue($channel->send($this->message('push body')));

        Http::assertSent(function ($request) {
            $bodyHasSecret = str_contains($request->body(), 'super-secret');

            return $request['app_id'] === 'app-1' && !$bodyHasSecret;
        });
    }

    public function test_apple_business_messages_requires_recipient_and_config(): void
    {
        Http::fake();

        $channel = new AppleBusinessMessagesChannel([
            'business_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'api_key' => 'k',
            'api_secret' => 's',
        ]);

        // No recipient_id option -> failure, no HTTP.
        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    public function test_apple_business_messages_success(): void
    {
        Http::fake(['mspgw.apple.com/*' => Http::response(['ok' => true], 200)]);

        $channel = new AppleBusinessMessagesChannel([
            'business_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'api_key' => 'k',
            'api_secret' => 's',
        ]);

        $message = new NotificationMessage('hi', options: ['recipient_id' => 'r-1']);

        $this->assertTrue($channel->send($message));

        Http::assertSent(fn ($request) => $request['businessId'] === 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function blockedUrlProvider(): array
    {
        return [
            'aws metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'localhost' => ['http://localhost/hook'],
            'rfc1918 10/8' => ['http://10.0.0.1/hook'],
            'rfc1918 192.168' => ['http://192.168.1.10/hook'],
            'loopback ip' => ['http://127.0.0.1/hook'],
            'ipv6 loopback' => ['http://[::1]/hook'],
            'file scheme' => ['file:///etc/passwd'],
            'gopher scheme' => ['gopher://example.com/'],
        ];
    }

    #[Group('security')]
    #[DataProvider('blockedUrlProvider')]
    public function test_webhook_ssrf_targets_are_blocked_without_http(string $url): void
    {
        Http::fake();

        $channel = new WebhookChannel(['url' => $url]);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    #[Group('security')]
    #[DataProvider('blockedUrlProvider')]
    public function test_slack_ssrf_targets_are_blocked_without_http(string $url): void
    {
        Http::fake();

        $channel = new SlackChannel(['webhook_url' => $url]);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    #[Group('security')]
    #[DataProvider('blockedUrlProvider')]
    public function test_discord_ssrf_targets_are_blocked_without_http(string $url): void
    {
        Http::fake();

        $channel = new DiscordChannel(['webhook_url' => $url]);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }

    #[Group('security')]
    #[DataProvider('blockedUrlProvider')]
    public function test_push_ssrf_targets_are_blocked_without_http(string $url): void
    {
        Http::fake();

        $channel = new PushChannel([
            'api_key' => 'k',
            'app_id' => 'a',
            'api_url' => $url,
        ]);

        $this->assertFalse($channel->send($this->message()));

        Http::assertNothingSent();
    }
}
