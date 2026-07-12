<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Avatar;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class AvatarServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(AvatarServiceInterface::class, AvatarService::class);
        $this->app->alias(AvatarServiceInterface::class, 'laranail.avatar');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            AvatarServiceInterface::class,
            'laranail.avatar',
        ];
    }
}
