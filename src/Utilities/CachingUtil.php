<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Utilities;

use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class CachingUtil
{
    public function __construct(protected int $defaultExpiration, protected array $defaultTags) {}

    /**
     * Cache data with configurable options.
     *
     * @return mixed
     */
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null)
    {
        // Use constructor defaults if parameters are null
        $minutes ??= $this->defaultExpiration;
        $tags ??= $this->defaultTags;

        // Convert minutes to seconds for Cache::put()
        $seconds = $minutes * 60;

        // Try to use tags if the store supports it and tags are provided
        if (Cache::getStore() instanceof TaggableStore && !empty($tags)) {
            try {
                Cache::tags($tags)->put($key, $data, $seconds);
            } catch (\Exception) {
                // Fallback to regular cache if tags fail
                Cache::put($key, $data, $seconds);
            }
        } else {
            Cache::put($key, $data, $seconds);
        }

        return $data;
    }

    /**
     * Retrieve cached data.
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null)
    {
        return Cache::get($key, $default);
    }

    /**
     * Forget cached data.
     *
     * @return void
     */
    public function forget(string $key)
    {
        Cache::forget($key);
    }
}
