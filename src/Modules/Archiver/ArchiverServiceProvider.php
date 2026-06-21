<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Archiver;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Contracts\ArchiverServiceInterface;
use Simtabi\Laranail\Toolkit\Modules\Archiver\Services\ArchiverService;

class ArchiverServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->app->bind(ArchiverServiceInterface::class, ArchiverService::class);
        $this->app->alias(ArchiverServiceInterface::class, 'laranail.archiver');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ArchiverServiceInterface::class,
            'laranail.archiver',
        ];
    }
}
