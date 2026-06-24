<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Simtabi\Laranail\Toolkit\Services\RouteService;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Drives RouteService against a real Router with defined routes so the
 * route-aware predicates (current route, prefixes, URL matching) exercise
 * their genuine code paths rather than mocks.
 */
class RouteServiceCoverageTest extends TestCase
{
    /**
     * Define a route group, dispatch a request through it so the Router has a
     * "current" route, and return a service bound to that same Router + Request.
     *
     * @param array<string, mixed> $query
     */
    private function serviceAtRoute(string $uri, string $name, array $query = [], ?string $prefix = null): RouteService
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $register = function (Router $router) use ($uri, $name): void {
            $router->get($uri, static fn () => 'ok')->name($name);
        };

        if ($prefix !== null) {
            $router->group(['prefix' => $prefix], $register);
        } else {
            $register($router);
        }

        $path = $prefix !== null ? trim($prefix, '/') . '/' . ltrim($uri, '/') : $uri;
        $request = Request::create('/' . ltrim($path, '/'), 'GET', $query);

        // Dispatch through the Router so Router::current()/currentRouteName()
        // resolve to the matched route, mirroring a real HTTP request. Bind the
        // request into the container too, so URL::current() reflects it.
        $router->dispatch($request);
        $this->app->instance('request', $request);

        return new RouteService($router, $request);
    }

    public function test_get_app_url_uses_request_host(): void
    {
        $request = Request::create('https://app.example.test/dashboard');
        $service = new RouteService($this->app->make(Router::class), $request);

        $this->assertSame('https://app.example.test', $service->getAppUrl());
    }

    public function test_is_route_matches_current_route_name(): void
    {
        $service = $this->serviceAtRoute('reports/sales', 'reports.sales');

        $this->assertTrue($service->isRoute('reports.sales'));
        $this->assertFalse($service->isRoute('reports.other'));
    }

    public function test_is_route_is_false_when_no_current_route(): void
    {
        $request = Request::create('/anything');
        $service = new RouteService($this->app->make(Router::class), $request);

        $this->assertFalse($service->isRoute('anything'));
    }

    public function test_is_url_segment_matches_with_prefix(): void
    {
        $service = $this->serviceAtRoute('users', 'admin.users', prefix: 'admin');

        // The route prefix "admin" is prepended to the segment pattern.
        $this->assertTrue($service->isUrlSegment('users'));
        $this->assertFalse($service->isUrlSegment('posts'));
    }

    public function test_is_url_segment_with_matching_query_parameter_short_circuits_true(): void
    {
        $service = $this->serviceAtRoute('users', 'users.index', query: ['tab' => 'active']);

        $this->assertTrue($service->isUrlSegment('users', paramKey: 'tab', paramValue: 'active'));
    }

    public function test_is_url_segment_is_false_when_no_current_route(): void
    {
        $request = Request::create('/users');
        $service = new RouteService($this->app->make(Router::class), $request);

        $this->assertFalse($service->isUrlSegment('users'));
    }

    public function test_active_css_class_for_url_parameter_matches_query_tab(): void
    {
        $service = $this->serviceAtRoute('settings', 'settings', query: ['tab' => 'profile']);

        $this->assertSame(' active ', $service->getActiveCssClassForUrlParameter('profile', key: 'tab'));
        $this->assertSame('', $service->getActiveCssClassForUrlParameter('billing', key: 'tab'));
    }

    public function test_active_css_class_for_url_parameter_matches_last_segment_when_value_blank(): void
    {
        $service = $this->serviceAtRoute('users/edit', 'users.edit');

        $this->assertSame(' active ', $service->getActiveCssClassForUrlParameter(null, segment: 'edit'));
        $this->assertSame('', $service->getActiveCssClassForUrlParameter(null, segment: 'create'));
    }

    public function test_get_current_route_info_exposes_name_method_and_path(): void
    {
        $service = $this->serviceAtRoute('reports/sales', 'reports.sales');

        $info = $service->getCurrentRouteInfo();

        $this->assertSame('reports.sales', $info->name);
        $this->assertSame('GET', $info->method);
        $this->assertIsArray($info->request);
        $this->assertSame('reports.sales', $info->request['name']);
        $this->assertSame('reports/sales', $info->request['path']);
    }

    public function test_get_route_name_from_url_resolves_a_defined_route(): void
    {
        $service = $this->serviceAtRoute('catalog/items', 'catalog.items');

        $this->assertSame('catalog.items', $service->getRouteNameFromUrl('/catalog/items'));
    }

    public function test_get_route_name_from_url_returns_null_for_unknown_url(): void
    {
        $service = $this->serviceAtRoute('catalog/items', 'catalog.items');

        $this->assertNull($service->getRouteNameFromUrl('/does/not/exist'));
    }

    public function test_active_menu_class_name_matches_current_route_name(): void
    {
        $service = $this->serviceAtRoute('dashboard', 'dashboard');

        $this->assertSame('active', $service->getActiveMenuClassName('dashboard'));
    }

    public function test_active_menu_class_name_falls_back_to_url_contains(): void
    {
        $service = $this->serviceAtRoute('account/profile', 'account.profile');

        // The current route name is "account.profile" (no match), but the URL
        // contains "account/profile" so the Str::contains branch returns active.
        $this->assertSame('active', $service->getActiveMenuClassName('account/profile'));
        $this->assertSame('', $service->getActiveMenuClassName('totally/unrelated'));
    }
}
