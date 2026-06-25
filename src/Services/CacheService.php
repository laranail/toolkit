<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Simtabi\Laranail\Toolkit\Services\Contracts\CacheRepositoryInterface;
use Throwable;

/**
 * Cache helper with configurable expiration, optional tagging, key namespacing
 * and a resilient (log-and-fall-back) failure mode.
 *
 * The original `cache()`/`get()`/`forget()` surface is preserved verbatim. The
 * `remember`/`put`/`many`/`increment`/`decrement` + fluent `tags()` methods are
 * the capability folded in from the legacy `Foundation\Services\CacheService`
 * (namespacing prefix + injected-logger fallback included).
 */
class CacheService implements CacheRepositoryInterface
{
    /**
     * Tag group applied by the fluent {@see tags()} helper (separate from the
     * constructor default tags, which feed only the {@see self::cache()} path).
     *
     * @var list<string>
     */
    protected array $tagGroup = [];

    private LoggerInterface $logger;

    /**
     * @param array<int, string> $defaultTags
     */
    public function __construct(
        protected int $defaultExpiration,
        protected array $defaultTags,
        ?LoggerInterface $logger = null,
        protected string $namespace = '',
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Cache data with configurable options.
     *
     * @param array<int, string>|null $tags
     */
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null): mixed
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
     * Preserves the original facade-direct behaviour (no namespacing prefix) so
     * the published `cache()`/`get()` round-trip is unchanged. Use the
     * namespace-aware methods ({@see remember()}, {@see put()}, …) when prefixing
     * is desired.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * Forget cached data (facade-direct, no namespacing — see {@see get()}).
     */
    public function forget(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * Remember a value: return the cached entry or compute, store and return it.
     *
     * On any cache backend failure the callback result is returned directly so
     * callers never see an exception bubble out of a cache call.
     */
    public function remember(string $key, callable $callback, ?int $minutes = null): mixed
    {
        $minutes ??= $this->defaultExpiration;
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->remember($namespacedKey, $minutes * 60, Closure::fromCallable($callback));
        } catch (Throwable $e) {
            $this->logger->error('Cache remember failed', ['key' => $key, 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Remember a value forever (until manually forgotten).
     */
    public function rememberForever(string $key, callable $callback): mixed
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->rememberForever($namespacedKey, Closure::fromCallable($callback));
        } catch (Throwable $e) {
            $this->logger->error('Cache remember forever failed', ['key' => $key, 'error' => $e->getMessage()]);

            return $callback();
        }
    }

    /**
     * Store a value, returning whether the write succeeded.
     */
    public function put(string $key, mixed $value, ?int $minutes = null): bool
    {
        $minutes ??= $this->defaultExpiration;
        $namespacedKey = $this->namespacedKey($key);

        try {
            return $this->store()->put($namespacedKey, $value, $minutes * 60);
        } catch (Throwable $e) {
            $this->logger->error('Cache put failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get multiple values at once, keyed by their original (un-namespaced) keys.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys, mixed $default = null): array
    {
        try {
            $store = $this->store();

            $output = [];
            foreach ($keys as $key) {
                $output[$key] = $store->get($this->namespacedKey($key), $default);
            }

            return $output;
        } catch (Throwable $e) {
            $this->logger->error('Cache many failed', ['keys' => $keys, 'error' => $e->getMessage()]);

            return array_fill_keys($keys, $default);
        }
    }

    /**
     * Increment a numeric cache value, or false on failure.
     */
    public function increment(string $key, int $value = 1): int|false
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            $result = $this->store()->increment($namespacedKey, $value);

            return is_int($result) ? $result : false;
        } catch (Throwable $e) {
            $this->logger->error('Cache increment failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Decrement a numeric cache value, or false on failure.
     */
    public function decrement(string $key, int $value = 1): int|false
    {
        $namespacedKey = $this->namespacedKey($key);

        try {
            $result = $this->store()->decrement($namespacedKey, $value);

            return is_int($result) ? $result : false;
        } catch (Throwable $e) {
            $this->logger->error('Cache decrement failed', ['key' => $key, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Return a clone scoped to the given tags for grouped invalidation.
     *
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static
    {
        $clone = clone $this;
        $clone->tagGroup = array_values($tags);

        return $clone;
    }

    /**
     * Apply the configured namespace prefix to a key.
     */
    protected function namespacedKey(string $key): string
    {
        return $this->namespace !== '' ? $this->namespace . ':' . $key : $key;
    }

    /**
     * Resolve the cache repository, applying the fluent tag scope when the store
     * is taggable and tags are present.
     */
    protected function store(): Repository
    {
        if ($this->tagGroup !== [] && Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($this->tagGroup);
        }

        return Cache::store();
    }
}
