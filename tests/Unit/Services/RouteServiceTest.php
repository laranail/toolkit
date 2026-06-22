<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use PHPUnit\Framework\TestCase;
use Simtabi\Laranail\Toolkit\Services\RouteService;

class RouteServiceTest extends TestCase
{
    public function test_is_current_route_matches_via_request_pattern(): void
    {
        $request = Request::create('/users/5/edit');

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertTrue($service->isCurrentRoute('users/*'));
        $this->assertFalse($service->isCurrentRoute('posts/*'));
    }

    public function test_is_current_route_falls_back_to_route_name(): void
    {
        $request = Request::create('/dashboard');

        $router = $this->createMock(Router::class);
        $router->method('currentRouteName')->willReturn('dashboard');

        $service = new RouteService($router, $request);

        // URL pattern would not match the *name* "settings"; the name fallback does.
        $this->assertTrue($service->isCurrentRoute('settings.x') === false);
        $this->assertTrue($service->isCurrentRoute('dashboard'));
    }

    public function test_active_css_class_returns_class_only_when_active(): void
    {
        $request = Request::create('/home');

        $router = $this->createMock(Router::class);
        $router->method('currentRouteName')->willReturn(null);

        $service = new RouteService($router, $request);

        $this->assertSame('active', $service->getActiveCssClass('home'));
        $this->assertSame('current', $service->getActiveCssClassForRoute('home', 'current'));
        $this->assertSame('', $service->getActiveCssClass('about'));
    }

    public function test_last_url_segment(): void
    {
        $request = Request::create('/admin/users/edit');

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertTrue($service->isLastUrlSegment('edit'));
        $this->assertFalse($service->isLastUrlSegment('users'));
    }

    public function test_url_request_segment_is_one_based_offset(): void
    {
        $request = Request::create('/admin/users/edit');

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertTrue($service->isUrlRequestSegment('admin', 0));
        $this->assertTrue($service->isUrlRequestSegment('users', 1));
        $this->assertFalse($service->isUrlRequestSegment('users', 0));
    }

    public function test_request_parameter_helpers(): void
    {
        $request = Request::create('/search', 'GET', ['tab' => 'open']);

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertTrue($service->isRequestParameter('tab', 'open'));
        $this->assertFalse($service->isRequestParameter('tab', 'closed'));
        $this->assertSame('open', $service->getRequestParameterValue('tab'));
        $this->assertNull($service->getRequestParameterValue('missing'));
    }

    public function test_get_input_value_from_query_string_coerces_non_strings_to_empty(): void
    {
        $request = Request::create('/search', 'GET', ['q' => 'hello', 'n' => ['x']]);

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertSame('hello', $service->getInputValueFromQueryString('q'));
        $this->assertSame('', $service->getInputValueFromQueryString('n'));
    }

    public function test_active_menu_class_matches_a_list_of_route_names_by_value(): void
    {
        // Regression: legacy used Arr::has() (key check) on a flat value list.
        $request = Request::create('/users');

        $router = $this->createMock(Router::class);
        $router->method('currentRouteName')->willReturn('users.index');

        $service = new RouteService($router, $request);

        $this->assertSame('active', $service->getActiveMenuClassName(['users.index', 'users.create']));
        $this->assertSame('', $service->getActiveMenuClassName(['posts.index']));
    }

    public function test_is_request_returns_bool_or_padded_class(): void
    {
        $request = Request::create('/admin/users');

        $service = new RouteService($this->createMock(Router::class), $request);

        $this->assertTrue($service->isRequest('admin/*', returnBool: true));
        $this->assertSame(' on ', $service->isRequest('admin/*', 'on'));
        $this->assertSame('', $service->isRequest('reports/*', 'on'));
    }

    public function test_is_request_on_page_compares_name_or_url(): void
    {
        $request = Request::create('https://example.test/contact');

        $router = $this->createMock(Router::class);
        $router->method('currentRouteName')->willReturn('contact');

        $service = new RouteService($router, $request);

        $this->assertTrue($service->isRequestOnPage('contact'));
        $this->assertTrue($service->isRequestOnPage('https://example.test/contact', 'url'));
        $this->assertFalse($service->isRequestOnPage('about'));
    }
}
