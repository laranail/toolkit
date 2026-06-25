<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services\Contracts;

use Simtabi\Laranail\Toolkit\Services\CacheService;

/**
 * Public surface of the toolkit's {@see CacheService}.
 *
 * Named to avoid colliding with Illuminate's
 * `Illuminate\Contracts\Cache\Repository`: this is the toolkit's own resilient
 * (log-and-fall-back), namespaced, optionally-tagged cache helper, not a drop-in
 * for the framework repository. Bound interface→{@see CacheService}.
 */
interface CacheRepositoryInterface
{
    /**
     * Cache data with configurable options.
     *
     * @param array<int, string>|null $tags
     */
    public function cache(string $key, mixed $data, ?int $minutes = null, ?array $tags = null): mixed;

    /** Retrieve cached data (facade-direct, no namespacing). */
    public function get(string $key, mixed $default = null): mixed;

    /** Forget cached data (facade-direct, no namespacing). */
    public function forget(string $key): void;

    /**
     * Remember a value: return the cached entry or compute, store and return it.
     */
    public function remember(string $key, callable $callback, ?int $minutes = null): mixed;

    /** Remember a value forever (until manually forgotten). */
    public function rememberForever(string $key, callable $callback): mixed;

    /** Store a value, returning whether the write succeeded. */
    public function put(string $key, mixed $value, ?int $minutes = null): bool;

    /**
     * Get multiple values at once, keyed by their original (un-namespaced) keys.
     *
     * @param list<string> $keys
     *
     * @return array<string, mixed>
     */
    public function many(array $keys, mixed $default = null): array;

    /** Increment a numeric cache value, or false on failure. */
    public function increment(string $key, int $value = 1): int|false;

    /** Decrement a numeric cache value, or false on failure. */
    public function decrement(string $key, int $value = 1): int|false;

    /**
     * Return a clone scoped to the given tags for grouped invalidation.
     *
     * @param array<int, string> $tags
     */
    public function tags(array $tags): static;
}
