<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Simtabi\Laranail\Toolkit\Services\Contracts\SessionServiceInterface;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class SessionServiceTest extends TestCase
{
    private function service(): SessionServiceInterface
    {
        return $this->app->make(SessionServiceInterface::class);
    }

    public function test_resolves_through_the_contract(): void
    {
        $this->assertInstanceOf(SessionServiceInterface::class, $this->service());
    }

    public function test_exists_in_filter_key(): void
    {
        $this->assertTrue($this->service()->existsInFilterKey('a&b&c', 'b'));
        $this->assertFalse($this->service()->existsInFilterKey('a&b&c', 'z'));
    }

    public function test_join_in_filter_key_drops_nulls(): void
    {
        $this->assertSame('a&b&c', $this->service()->joinInFilterKey('a', null, 'b', null, 'c'));
        $this->assertSame('', $this->service()->joinInFilterKey(null, null));
    }

    public function test_remove_from_filter_key_rebuilds_or_nulls(): void
    {
        $this->assertSame('a&c', $this->service()->removeFromFilterKey('a&b&c', 'b'));
        // Removing the lone token (and the reserved page token) leaves nothing.
        $this->assertNull($this->service()->removeFromFilterKey('b&page', 'b'));
    }

    public function test_save_javascript_cookies_persists_session_and_queues_cookie(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', ['theme' => 'dark']));

        // Resolve AFTER swapping the request so the service injects this instance.
        $this->app->make(SessionServiceInterface::class)->saveJavaScriptCookies('theme', 30);

        $this->assertSame('dark', Session::get('theme'));
        $this->assertTrue(Cookie::hasQueued('theme'));
    }

    public function test_save_javascript_cookies_is_a_noop_when_absent(): void
    {
        $this->app->instance('request', Request::create('/', 'GET'));

        $this->app->make(SessionServiceInterface::class)->saveJavaScriptCookies('missing');

        $this->assertNull(Session::get('missing'));
        $this->assertFalse(Cookie::hasQueued('missing'));
    }
}
