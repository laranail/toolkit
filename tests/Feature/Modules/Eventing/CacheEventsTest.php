<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Modules\Eventing;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Event;
use Simtabi\Laranail\Toolkit\Enums\CacheAction;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\CacheEvents;
use Simtabi\Laranail\Toolkit\Modules\Eventing\Events\Event as BaseEvent;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CacheEventsTest extends TestCase
{
    public function test_it_extends_the_dispatchable_base_event(): void
    {
        $this->assertInstanceOf(BaseEvent::class, CacheEvents::cleared());
        $this->assertContains(Dispatchable::class, array_values(class_uses_recursive(CacheEvents::class)));
    }

    public function test_clearing_factory(): void
    {
        $event = CacheEvents::clearing(['store' => 'redis']);

        $this->assertSame(CacheAction::Clearing, $event->action);
        $this->assertSame(['store' => 'redis'], $event->metadata);
        $this->assertSame('Cache Clearing Started', $event->getDisplayName());
        $this->assertSame('low', $event->getPriorityLevel());
        $this->assertSame('in_progress', $event->getResult());
        $this->assertFalse($event->isSuccessful());
    }

    public function test_cleared_factory(): void
    {
        $event = CacheEvents::cleared(['tags' => ['users']]);

        $this->assertSame(CacheAction::Cleared, $event->action);
        $this->assertSame('Cache Cleared', $event->getDisplayName());
        $this->assertSame('medium', $event->getPriorityLevel());
        $this->assertSame('success', $event->getResult());
        $this->assertTrue($event->isSuccessful());
    }

    public function test_failed_factory_carries_reason(): void
    {
        $event = CacheEvents::failed('store unreachable', ['store' => 'redis']);

        $this->assertSame(CacheAction::Failed, $event->action);
        $this->assertSame('store unreachable', $event->metadata['reason']);
        $this->assertSame('redis', $event->metadata['store']);
        $this->assertSame('high', $event->getPriorityLevel());
        $this->assertSame('failure', $event->getResult());
        $this->assertFalse($event->isSuccessful());
        $this->assertStringContainsString('store unreachable', $event->getDescription());
    }

    public function test_descriptions_for_non_failed_actions(): void
    {
        $this->assertStringContainsString('started', strtolower(CacheEvents::clearing()->getDescription()));
        $this->assertStringContainsString('cleared', strtolower(CacheEvents::cleared()->getDescription()));
    }

    public function test_failed_description_handles_missing_reason(): void
    {
        $event = new CacheEvents(CacheAction::Failed);

        $this->assertStringContainsString('Unknown error', $event->getDescription());
    }

    public function test_events_are_dispatchable(): void
    {
        Event::fake();

        event(CacheEvents::cleared(['tags' => ['users']]));

        Event::assertDispatched(
            CacheEvents::class,
            static fn (CacheEvents $e): bool => $e->action === CacheAction::Cleared
                && $e->metadata === ['tags' => ['users']],
        );
    }

    public function test_static_dispatch_builds_from_constructor_args(): void
    {
        Event::fake();

        CacheEvents::dispatch(CacheAction::Failed, ['reason' => 'boom']);

        Event::assertDispatched(
            CacheEvents::class,
            static fn (CacheEvents $e): bool => $e->action === CacheAction::Failed,
        );
    }

    public function test_cache_action_enum_exposes_metadata(): void
    {
        $this->assertSame('Cache Operation Failed', CacheAction::Failed->displayName());
        $this->assertSame('high', CacheAction::Failed->priority());
        $this->assertSame('failure', CacheAction::Failed->result());
        $this->assertStringContainsString('failed', strtolower(CacheAction::Failed->description()));
    }
}
