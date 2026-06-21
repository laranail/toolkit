<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Macros;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;

/**
 * Coordinator that wires every grouped macro provider in the toolkit and
 * applies the factory mixin.
 *
 * Macros must be available globally, so this provider is registered eagerly
 * (it is not deferrable).
 */
final class MacroServiceProvider extends ServiceProvider
{
    /**
     * Grouped macro providers registered by this coordinator.
     *
     * @var list<class-string<ServiceProvider>>
     */
    private const MACRO_PROVIDERS = [
        StringMacros::class,
        CollectionMacros::class,
        ArrMacros::class,
        QueryBuilderMacros::class,
        BlueprintMacros::class,
        RequestMacros::class,
    ];

    public function register(): void
    {
        foreach (self::MACRO_PROVIDERS as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if (class_exists(Factory::class)) {
            Factory::mixin(new FactoryBuilderMixin());
        }
    }
}
