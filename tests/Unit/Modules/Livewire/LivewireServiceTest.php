<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Modules\Livewire;

use Illuminate\Container\Container;
use Simtabi\Laranail\Toolkit\Modules\Livewire\HasLivewireComponents;
use Simtabi\Laranail\Toolkit\Modules\Livewire\Livewire as LivewireFacade;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireService;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Livewire\LivewireServiceProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class LivewireServiceTest extends TestCase
{
    public function test_register_component_collects_the_pair(): void
    {
        $service = new LivewireService();

        $returned = $service->registerComponent(' my-component ', FakeComponent::class);

        $this->assertSame($service, $returned);
        $this->assertSame(
            ['my-component' => FakeComponent::class],
            $service->getRegisteredComponents(),
        );
    }

    public function test_register_components_merges_a_map(): void
    {
        $service = new LivewireService();

        $service->registerComponent('cart', FakeComponent::class)
            ->registerComponents([
                'user-card' => FakeComponent::class,
                'widget' => FakeComponent::class,
            ]);

        $this->assertSame(
            [
                'cart' => FakeComponent::class,
                'user-card' => FakeComponent::class,
                'widget' => FakeComponent::class,
            ],
            $service->getRegisteredComponents(),
        );
    }

    public function test_register_component_overwrites_a_duplicate_alias(): void
    {
        $service = new LivewireService();

        $service->registerComponent('cart', FakeComponent::class)
            ->registerComponent('cart', OtherComponent::class);

        $this->assertSame(
            ['cart' => OtherComponent::class],
            $service->getRegisteredComponents(),
        );
    }

    public function test_generate_component_key_kebab_cases_the_name(): void
    {
        $service = new LivewireService();

        $this->assertSame('my-component', $service->generateComponentKey('MyComponent'));
        $this->assertSame('user-card', $service->generateComponentKey('UserCard'));
    }

    public function test_livewire_is_not_available_in_the_test_environment(): void
    {
        $this->assertFalse(new LivewireService()->isLivewireAvailable());
    }

    public function test_flush_is_a_safe_no_op_without_livewire(): void
    {
        $service = new LivewireService();
        $service->registerComponent('my-component', FakeComponent::class);

        $service->flush();

        // No exception is thrown and the collected map is untouched.
        $this->assertSame(
            ['my-component' => FakeComponent::class],
            $service->getRegisteredComponents(),
        );
    }

    public function test_provider_binds_the_interface_and_alias(): void
    {
        $this->assertInstanceOf(LivewireService::class, $this->app->make(LivewireServiceInterface::class));
        $this->assertInstanceOf(LivewireService::class, $this->app->make('laranail.livewire'));
    }

    public function test_interface_is_resolved_as_a_singleton(): void
    {
        $this->assertSame(
            $this->app->make(LivewireServiceInterface::class),
            $this->app->make(LivewireServiceInterface::class),
        );
    }

    public function test_facade_resolves_its_root_and_proxies_calls(): void
    {
        $this->assertInstanceOf(LivewireServiceInterface::class, LivewireFacade::getFacadeRoot());
        $this->assertSame('my-component', LivewireFacade::generateComponentKey('MyComponent'));
        $this->assertFalse(LivewireFacade::isLivewireAvailable());
    }

    public function test_trait_registers_host_declared_components(): void
    {
        $host = new FakeLivewireHost();

        $host->registerLivewireComponents();

        $this->assertSame(
            ['widget' => FakeComponent::class],
            $this->app->make(LivewireServiceInterface::class)->getRegisteredComponents(),
        );
    }

    public function test_trait_is_a_no_op_without_the_host_hook(): void
    {
        $host = new FakeLivewireHostWithoutHook();

        $host->registerLivewireComponents();

        $this->assertSame([], $this->app->make(LivewireServiceInterface::class)->getRegisteredComponents());
    }

    public function test_trait_is_a_no_op_for_an_empty_component_map(): void
    {
        $host = new FakeLivewireHostEmpty();

        $host->registerLivewireComponents();

        $this->assertSame([], $this->app->make(LivewireServiceInterface::class)->getRegisteredComponents());
    }

    public function test_provider_register_binds_interface_singleton_and_alias(): void
    {
        $app = new Container();

        new LivewireServiceProvider($app)->register();

        $this->assertInstanceOf(LivewireService::class, $app->make(LivewireServiceInterface::class));
        $this->assertSame(
            $app->make(LivewireServiceInterface::class),
            $app->make('laranail.livewire'),
        );
    }

    public function test_provider_provides_its_deferred_bindings(): void
    {
        $provides = new LivewireServiceProvider($this->app)->provides();

        $this->assertSame(
            [LivewireServiceInterface::class, 'laranail.livewire'],
            $provides,
        );
    }

    public function test_flush_registers_every_component_when_livewire_is_available(): void
    {
        $service = new FakeAvailableLivewireService();
        $service->registerComponents([
            'my-component' => FakeComponent::class,
            'widget' => OtherComponent::class,
        ]);

        $service->flush();

        $this->assertTrue($service->isLivewireAvailable());
        $this->assertSame(
            [
                'my-component' => FakeComponent::class,
                'widget' => OtherComponent::class,
            ],
            $service->registered,
        );
    }

    public function test_provider_boot_flushes_when_livewire_is_available(): void
    {
        $service = new FakeAvailableLivewireService();
        $service->registerComponent('my-component', FakeComponent::class);
        $this->app->instance(LivewireServiceInterface::class, $service);

        new LivewireServiceProvider($this->app)->boot();

        $this->assertSame(['my-component' => FakeComponent::class], $service->registered);
    }

    public function test_provider_boot_is_a_no_op_when_livewire_is_absent(): void
    {
        $service = new LivewireService();
        $service->registerComponent('my-component', FakeComponent::class);
        $this->app->instance(LivewireServiceInterface::class, $service);

        new LivewireServiceProvider($this->app)->boot();

        // Still collected, never flushed into a (non-existent) registry.
        $this->assertSame(['my-component' => FakeComponent::class], $service->getRegisteredComponents());
    }
}

/**
 * Test double that pretends Livewire is installed and records the alias → class
 * pairs that {@see LivewireService::flush()} would push into Livewire, so the
 * flush loop is exercised without the livewire/livewire package present.
 */
final class FakeAvailableLivewireService extends LivewireService
{
    /** @var array<string, string> */
    public array $registered = [];

    public function isLivewireAvailable(): bool
    {
        return true;
    }

    protected function registerWithLivewire(string $alias, string $class): void
    {
        $this->registered[$alias] = $class;
    }
}

final class FakeComponent {}

final class OtherComponent {}

final class FakeLivewireHost
{
    use HasLivewireComponents;

    /**
     * @return array<string, string>
     */
    public function getLivewireComponents(): array
    {
        return ['widget' => FakeComponent::class];
    }
}

final class FakeLivewireHostEmpty
{
    use HasLivewireComponents;

    /**
     * @return array<string, string>
     */
    public function getLivewireComponents(): array
    {
        return [];
    }
}

final class FakeLivewireHostWithoutHook
{
    use HasLivewireComponents;
}
