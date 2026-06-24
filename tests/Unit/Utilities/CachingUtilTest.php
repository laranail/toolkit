<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Psr\Log\AbstractLogger;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\CachingUtil;
use Simtabi\Laranail\Toolkit\Utilities\Contracts\CacheRepositoryInterface;

class CachingUtilTest extends TestCase
{
    private CachingUtil $cachingUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachingUtil = new CachingUtil(60, ['default']);
        Cache::flush();
    }

    public function test_cache_repository_contract_resolves_to_the_caching_util(): void
    {
        $resolved = $this->app->make(CacheRepositoryInterface::class);

        $this->assertInstanceOf(CachingUtil::class, $resolved);
    }

    public function test_can_cache_data_with_default_expiration()
    {
        $key = 'test_key';
        $data = ['test' => 'data'];

        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);
        Cache::shouldReceive('get')->with($key)->andReturn($data);

        $result = $this->cachingUtil->cache($key, $data);

        $this->assertEquals($data, $result);
        $this->assertEquals($data, Cache::get($key));
    }

    public function test_can_cache_data_with_custom_expiration()
    {
        $key = 'test_key_custom';
        $data = ['test' => 'data'];
        $minutes = 30;

        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, $minutes * 60)->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);
        Cache::shouldReceive('get')->with($key)->andReturn($data);

        $result = $this->cachingUtil->cache($key, $data, $minutes);

        $this->assertEquals($data, $result);
        $this->assertEquals($data, Cache::get($key));
    }

    public function test_can_cache_data_with_custom_tags()
    {
        $key = 'test_key_tags';
        $data = ['test' => 'data'];
        $tags = ['custom', 'test'];

        $result = $this->cachingUtil->cache($key, $data, null, $tags);

        $this->assertEquals($data, $result);
    }

    public function test_can_retrieve_cached_data()
    {
        $key = 'test_key_get';
        $data = ['test' => 'data'];

        // Mock Cache facade
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data);

        $this->cachingUtil->cache($key, $data);
        $result = $this->cachingUtil->get($key);

        $this->assertEquals($data, $result);
    }

    public function test_returns_default_when_key_not_found()
    {
        $key = 'non_existent_key';
        $default = 'default_value';

        // Mock Cache facade
        Cache::shouldReceive('get')->with($key, $default)->andReturn($default);

        $result = $this->cachingUtil->get($key, $default);

        $this->assertEquals($default, $result);
    }

    public function test_can_forget_cached_data()
    {
        $key = 'test_key_forget';
        $data = ['test' => 'data'];

        // Mock Cache facade - first call returns data, after forget returns null
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('put')->with($key, $data, \Mockery::any())->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn($data)->once();
        Cache::shouldReceive('forget')->with($key)->andReturn(true);
        Cache::shouldReceive('get')->with($key, null)->andReturn(null)->once();

        $this->cachingUtil->cache($key, $data);
        $this->assertEquals($data, $this->cachingUtil->get($key));

        $this->cachingUtil->forget($key);
        $this->assertNull($this->cachingUtil->get($key));
    }

    public function test_handles_taggable_store_gracefully()
    {
        $key = 'test_key_taggable';
        $data = ['test' => 'data'];
        $tags = ['test'];

        // Mock Cache facade to avoid store issues
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('put')->andReturn(true);

        $result = $this->cachingUtil->cache($key, $data, null, $tags);

        $this->assertEquals($data, $result);
    }

    // --- Folded-in legacy delta (remember/put/many/inc/dec/tags/namespacing) ---

    public function test_remember_computes_stores_and_returns_value(): void
    {
        $calls = 0;
        $compute = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        $this->assertSame('computed', $this->cachingUtil->remember('rk', $compute));
        // Second call hits the cache; the callback must not run again.
        $this->assertSame('computed', $this->cachingUtil->remember('rk', $compute));
        $this->assertSame(1, $calls);
    }

    public function test_remember_forever_caches_value(): void
    {
        $value = $this->cachingUtil->rememberForever('rfk', fn () => 'forever');

        $this->assertSame('forever', $value);
        $this->assertSame('forever', $this->cachingUtil->get('rfk'));
    }

    public function test_put_writes_value_and_returns_true(): void
    {
        $this->assertTrue($this->cachingUtil->put('pk', 'pv'));
        $this->assertSame('pv', $this->cachingUtil->get('pk'));
    }

    public function test_many_returns_values_keyed_by_original_keys(): void
    {
        $this->cachingUtil->put('a', 1);
        $this->cachingUtil->put('b', 2);

        $this->assertSame(
            ['a' => 1, 'b' => 2, 'missing' => 'def'],
            $this->cachingUtil->many(['a', 'b', 'missing'], 'def'),
        );
    }

    public function test_increment_and_decrement(): void
    {
        $this->cachingUtil->put('counter', 5);

        $this->assertSame(6, $this->cachingUtil->increment('counter'));
        $this->assertSame(4, $this->cachingUtil->decrement('counter', 2));
    }

    public function test_namespace_prefix_is_applied_to_namespaced_writes(): void
    {
        $namespaced = new CachingUtil(60, [], null, 'app1');
        $namespaced->put('shared', 'one');

        // put() prefixes the key; the value lands under "app1:shared", not "shared".
        $this->assertSame('one', Cache::get('app1:shared'));
        $this->assertNull(Cache::get('shared'));

        // remember() reads back via the same prefix.
        $this->assertSame('one', $namespaced->remember('shared', fn () => 'recomputed'));
    }

    public function test_tags_returns_a_distinct_clone(): void
    {
        $tagged = $this->cachingUtil->tags(['x', 'y']);

        $this->assertInstanceOf(CachingUtil::class, $tagged);
        $this->assertNotSame($this->cachingUtil, $tagged);
    }

    public function test_remember_falls_back_to_callback_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        // Force the underlying store to throw on remember().
        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('tags')->andReturnSelf();
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('backend down'));

        $result = $util->remember('boom', fn () => 'fallback');

        $this->assertSame('fallback', $result);
        $this->assertNotEmpty($logger->errors);
    }

    public function test_cache_uses_non_taggable_store_path(): void
    {
        // A non-taggable store (and/or no tags) falls through to Cache::put().
        $util = new CachingUtil(60, []);

        $this->assertSame('plain', $util->cache('ck', 'plain'));
        $this->assertSame('plain', $util->get('ck'));
    }

    public function test_remember_forever_falls_back_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('down'));

        $this->assertSame('fb', $util->rememberForever('rf', fn () => 'fb'));
        $this->assertNotEmpty($logger->errors);
    }

    public function test_put_returns_false_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('down'));

        $this->assertFalse($util->put('pk', 'pv'));
        $this->assertNotEmpty($logger->errors);
    }

    public function test_many_returns_defaults_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('down'));

        $this->assertSame(['a' => null, 'b' => null], $util->many(['a', 'b']));
        $this->assertNotEmpty($logger->errors);
    }

    public function test_increment_returns_false_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('down'));

        $this->assertFalse($util->increment('ck'));
        $this->assertNotEmpty($logger->errors);
    }

    public function test_decrement_returns_false_and_logs_on_store_failure(): void
    {
        $logger = new CollectingTestLogger();
        $util = new CachingUtil(60, [], $logger);

        Cache::shouldReceive('getStore')->andReturn(new ArrayStore());
        Cache::shouldReceive('store')->andThrow(new \RuntimeException('down'));

        $this->assertFalse($util->decrement('ck'));
        $this->assertNotEmpty($logger->errors);
    }

    public function test_tagged_clone_uses_taggable_store_for_namespaced_writes(): void
    {
        // The array driver IS taggable, so a tagged clone routes through
        // Cache::tags() inside store() and round-trips under the tag group.
        $tagged = new CachingUtil(60, [])->tags(['group-a']);

        $this->assertTrue($tagged->put('tk', 'tv'));
        $this->assertSame('tv', $tagged->remember('tk', fn () => 'recomputed'));
    }
}

/**
 * Minimal PSR-3 logger that records `error`-level messages for assertions.
 */
class CollectingTestLogger extends AbstractLogger
{
    /** @var list<string> */
    public array $errors = [];

    /**
     * @param mixed              $level
     * @param string|\Stringable $message
     * @param array<mixed>       $context
     */
    public function log($level, $message, array $context = []): void
    {
        if ($level === 'error') {
            $this->errors[] = (string) $message;
        }
    }
}
