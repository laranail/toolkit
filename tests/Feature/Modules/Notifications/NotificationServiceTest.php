<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Notifications;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\LogChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Channels\SlackChannel;
use Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Jobs\SendQueuedNotification;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    public function test_config_is_merged_under_the_module_namespace(): void
    {
        $this->assertIsArray(config('laranail.toolkit.notifications.channels'));
        $this->assertArrayHasKey('log', config('laranail.toolkit.notifications.channels'));
    }

    public function test_service_resolves_from_the_container(): void
    {
        $this->assertInstanceOf(NotificationService::class, $this->app->make(NotificationService::class));
        $this->assertInstanceOf(NotificationService::class, $this->app->make('laranail.notifications'));
    }

    public function test_known_channels_resolve_from_config(): void
    {
        $service = new NotificationService([
            'channels' => [
                'log' => ['enabled' => true],
                'slack' => ['enabled' => true, 'webhook_url' => 'https://hooks.slack.com/x'],
            ],
        ]);

        $this->assertInstanceOf(LogChannel::class, $service->getChannel('log'));
        $this->assertInstanceOf(SlackChannel::class, $service->getChannel('slack'));
    }

    #[Group('security')]
    public function test_unknown_channel_in_config_is_silently_ignored_not_instantiated(): void
    {
        $service = new NotificationService([
            'channels' => [
                'evil' => ['class' => \stdClass::class],
            ],
        ]);

        // The arbitrary "class" key is never honoured; no channel registered.
        $this->assertNull($service->getChannel('evil'));
        $this->assertSame([], $service->getChannels());
    }

    #[Group('security')]
    public function test_register_channel_rejects_unknown_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NotificationService())->registerChannel('nope', new LogChannel());
    }

    #[Group('security')]
    public function test_set_default_channels_rejects_unknown_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new NotificationService())->setDefaultChannels(['log', 'bogus']);
    }

    public function test_channels_selector_accepts_string(): void
    {
        Log::shouldReceive('log')->once();

        $service = new NotificationService(['channels' => ['log' => ['enabled' => true]]]);

        $result = $service->send('hi', [], 'log');

        $this->assertTrue($result->isSuccessful());
        $this->assertSame(['log'], $result->getSuccessfulChannels());
    }

    public function test_channels_selector_accepts_array(): void
    {
        Log::shouldReceive('log')->once();

        $service = new NotificationService(['channels' => ['log' => ['enabled' => true]]]);

        $result = $service->send('hi', [], ['log']);

        $this->assertSame(['log'], $result->getSuccessfulChannels());
    }

    public function test_null_selector_falls_back_to_defaults(): void
    {
        Log::shouldReceive('log')->once();

        $service = new NotificationService([
            'channels' => ['log' => ['enabled' => true, 'default' => true]],
        ]);

        $result = $service->send('hi', [], null);

        $this->assertSame(['log'], $result->getSuccessfulChannels());
    }

    #[Group('security')]
    public function test_unregistered_channel_name_yields_error_not_silent_all(): void
    {
        $service = new NotificationService(['channels' => ['log' => ['enabled' => true]]]);

        // Sending to a channel that resolves to a valid key but was not
        // registered records an explicit error rather than fanning out.
        $result = $service->send('hi', [], 'slack');

        $this->assertArrayHasKey('slack', $result->getErrors());
        $this->assertSame('Channel not registered', $result->getErrors()['slack']);
    }

    public function test_queueing_dispatches_serializable_job_not_closure(): void
    {
        Queue::fake();

        $service = new NotificationService([
            'queueable' => true,
            'queue_name' => 'notifications',
            'channels' => ['log' => ['enabled' => true]],
        ]);

        $result = $service->send('queued body', [], 'log');

        $this->assertSame(['queued' => true], $result->getResults());

        Queue::assertPushed(SendQueuedNotification::class, function (SendQueuedNotification $job): bool {
            // The payload is a plain array (JSON-safe), not a closure.
            return is_array($job->message)
                && $job->message['body'] === 'queued body'
                && $job->channels === ['log']
                && ($job->message['options']['queued'] ?? false) === true;
        });
    }

    public function test_queued_job_handle_resolves_service_and_sends(): void
    {
        Log::spy();

        $job = new SendQueuedNotification(
            NotificationMessage::make('worker body', ['queued' => true])->toArray(),
            ['log'],
        );

        // Re-resolves a configured service from the container and delivers.
        $job->handle($this->app);

        // The LogChannel wrote the body through the log manager.
        Log::shouldHaveReceived('log')
            ->withArgs(fn ($level, $message) => $message === 'worker body')
            ->once();
    }

    public function test_disabled_channel_reports_false_without_sending(): void
    {
        $service = new NotificationService([
            'channels' => ['slack' => ['enabled' => false, 'webhook_url' => 'https://hooks.slack.com/x']],
        ]);

        $result = $service->send('hi', [], 'slack');

        $this->assertSame(['slack' => false], $result->getResults());
    }
}
