<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Gravatar;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class GravatarServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(GravatarServiceInterface::class, GravatarService::class);
        $this->app->alias(GravatarServiceInterface::class, 'laranail.gravatar');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            GravatarServiceInterface::class,
            'laranail.gravatar',
        ];
    }
}
