<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\BaseClasses;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Event;
use Mockery;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Events\Events;
use Simtabi\Laranail\Toolkit\Jobs\BaseJob;
use Simtabi\Laranail\Toolkit\Listeners\Listener;
use Simtabi\Laranail\Toolkit\Observers\Observer;
use Simtabi\Laranail\Toolkit\Services\LogService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class FixtureJob extends BaseJob
{
    public function handle(): void {}
}

class FixtureEvent extends Events
{
    public function __construct(public readonly string $payload = 'hi') {}
}

class FixtureListener extends Listener
{
    public bool $handled = false;

    public bool $gate = true;

    protected function shouldHandle(object $event): bool
    {
        return $this->gate;
    }

    protected function handleEvent(object $event): void
    {
        $this->handled = true;
    }
}

class FixtureObserver extends Observer {}

class BaseClassesTest extends TestCase
{
    public function test_base_job_implements_should_queue_with_defaults(): void
    {
        $job = new FixtureJob();

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame(10, $job->backoff);
        $this->assertSame(120, $job->timeout);
    }

    public function test_base_job_failed_logs_through_log_service(): void
    {
        $logs = Mockery::mock(LogManager::class);
        $logs->shouldReceive('log')->once()->withArgs(
            fn (string $level, string $message): bool => $level === 'error' && $message === 'kaboom'
        );

        $this->app->instance(LogService::class, new LogService($logs));

        new FixtureJob()->failed(new RuntimeException('kaboom'));
    }

    public function test_base_event_is_dispatchable(): void
    {
        $this->assertContains(Dispatchable::class, array_values(class_uses_recursive(Events::class)));

        Event::fake();

        FixtureEvent::dispatch('payload');

        Event::assertDispatched(FixtureEvent::class, fn (FixtureEvent $e): bool => $e->payload === 'payload');
    }

    public function test_base_listener_gate_short_circuits(): void
    {
        $listener = new FixtureListener();
        $listener->gate = false;
        $listener->handle(new FixtureEvent());
        $this->assertFalse($listener->handled);

        $listener->gate = true;
        $listener->handle(new FixtureEvent());
        $this->assertTrue($listener->handled);
    }

    public function test_base_observer_hooks_are_no_ops(): void
    {
        $observer = new FixtureObserver();

        // Conventional lifecycle hooks exist and are callable.
        $this->assertTrue(method_exists($observer, 'created'));
        $this->assertTrue(method_exists($observer, 'updated'));
        $this->assertTrue(method_exists($observer, 'deleted'));
    }
}
