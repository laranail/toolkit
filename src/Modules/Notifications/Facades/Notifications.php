<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Notifications\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Toolkit\Modules\Notifications\Services\NotificationService;

/**
 * @method static \Simtabi\Laranail\Toolkit\Modules\Notifications\Support\NotificationResult                            send(\Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage|string $message, array<string, mixed> $data = [], string|array<int, string>|null $channels = null, string $level = 'info')
 * @method static \Simtabi\Laranail\Toolkit\Modules\Notifications\Support\NotificationResult                            broadcast(\Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage|string $message, array<string, mixed> $data = [], string $level = 'info')
 * @method static \Simtabi\Laranail\Toolkit\Modules\Notifications\Support\NotificationResult                            dispatchNow(\Simtabi\Laranail\Toolkit\Modules\Notifications\DataTransferObjects\NotificationMessage $message, array<int, string> $channelNames)
 * @method static NotificationService                                                                                   registerChannel(string $name, \Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts\NotificationChannelInterface $channel)
 * @method static NotificationService                                                                                   unregisterChannel(string $name)
 * @method static NotificationService                                                                                   setDefaultChannels(array<int, string> $channels)
 * @method static \Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts\NotificationChannelInterface|null           getChannel(string $name)
 * @method static array<string, \Simtabi\Laranail\Toolkit\Modules\Notifications\Contracts\NotificationChannelInterface> getChannels()
 *
 * @see NotificationService
 */
class Notifications extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NotificationService::class;
    }
}
