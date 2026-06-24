<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Utilities;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Simtabi\Laranail\Toolkit\Utilities\SessionHelper;

class SessionHelperTest extends TestCase
{
    public function test_exists_in_filter_key(): void
    {
        $this->assertTrue(SessionHelper::existsInFilterKey('a&b&c', 'b'));
        $this->assertFalse(SessionHelper::existsInFilterKey('a&b&c', 'z'));
    }

    public function test_join_in_filter_key_drops_nulls(): void
    {
        $this->assertSame('a&b&c', SessionHelper::joinInFilterKey('a', null, 'b', null, 'c'));
        $this->assertSame('', SessionHelper::joinInFilterKey(null, null));
    }

    public function test_remove_from_filter_key_rebuilds_or_nulls(): void
    {
        $this->assertSame('a&c', SessionHelper::removeFromFilterKey('a&b&c', 'b'));
        // Removing the lone token (and the reserved page token) leaves nothing.
        $this->assertNull(SessionHelper::removeFromFilterKey('b&page', 'b'));
    }

    public function test_save_javascript_cookies_persists_session_and_queues_cookie(): void
    {
        $this->app->instance('request', Request::create('/', 'GET', ['theme' => 'dark']));

        SessionHelper::saveJavaScriptCookies('theme', 30);

        $this->assertSame('dark', Session::get('theme'));
        $this->assertTrue(Cookie::hasQueued('theme'));
    }

    public function test_save_javascript_cookies_is_a_noop_when_absent(): void
    {
        $this->app->instance('request', Request::create('/', 'GET'));

        SessionHelper::saveJavaScriptCookies('missing');

        $this->assertNull(Session::get('missing'));
        $this->assertFalse(Cookie::hasQueued('missing'));
    }
}
