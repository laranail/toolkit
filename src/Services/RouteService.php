<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Simtabi\Laranail\Toolkit\Services\Contracts\RouteServiceInterface;
use Throwable;

/**
 * Route checking and navigation helpers.
 *
 * The Router and Request are injected (no facades for the request state), so
 * the service is testable without booting the full HTTP kernel.
 */
final readonly class RouteService implements RouteServiceInterface
{
    public function __construct(
        private Router $router,
        private Request $request,
    ) {}

    public function getAppUrl(): string
    {
        $host = $this->request->getSchemeAndHttpHost();

        return $host !== '' ? $host : (string) config('app.url', 'http://localhost');
    }

    public function getActiveCssClassForRoute(string $routeName, string $class = 'active'): string
    {
        return $this->getActiveCssClass($routeName, $class);
    }

    public function isCurrentRoute(string $routeName): bool
    {
        // Request::is() supports exact names AND wildcards (e.g. "users.*").
        // Fall back to route-name matching when the URL pattern doesn't match.
        if ($this->request->is($routeName)) {
            return true;
        }

        return $this->router->currentRouteName() === $routeName;
    }

    public function isRoute(string $routeName): bool
    {
        $currentRoute = $this->router->current();

        if ($currentRoute === null) {
            return false;
        }

        return $currentRoute->getName() === $routeName;
    }

    public function getActiveCssClass(string $routeName, string $class = 'active'): string
    {
        return $this->isCurrentRoute($routeName) ? $class : '';
    }

    public function isUrlSegment(
        string $segment,
        bool $strict = false,
        ?string $paramKey = null,
        mixed $paramValue = null
    ): bool {
        $segment = trim(rtrim($segment, '/'));
        $route = $this->router->current();

        if ($route === null) {
            return false;
        }

        $prefix = trim($route->getPrefix() ?? '', '/');
        $prefix = $prefix !== '' ? "{$prefix}/" : '';

        $parameter = $paramKey !== null ? $this->request->query($paramKey) : null;
        $paramStatus = filled($parameter) && $this->looseEquals($parameter, $paramValue);

        $pattern = $strict
            ? [$prefix . $segment, $prefix . $segment . '/']
            : [$prefix . $segment, $prefix . $segment . '/', $prefix . $segment . '/*'];

        $matches = $this->request->is(...$pattern);

        if (!$paramStatus) {
            return $matches && blank($parameter) && blank($paramValue);
        }

        return true;
    }

    public function isLastUrlSegment(string $segment): bool
    {
        $segments = $this->request->segments();

        if ($segments === []) {
            return false;
        }

        return end($segments) === $segment;
    }

    public function isUrlRequestSegment(string $segment, int $position = 0): bool
    {
        return $this->request->segment($position + 1) === $segment;
    }

    public function isRequestParameter(string $key, mixed $value): bool
    {
        $keyValue = $this->request->all()[$key] ?? null;

        return filled($keyValue) && $this->looseEquals($keyValue, $value);
    }

    public function getRequestParameterValue(string $key): mixed
    {
        return $this->request->all()[trim($key)] ?? null;
    }

    public function getActiveCssClassForUrlParameter(
        mixed $value,
        ?string $segment = null,
        string $key = 'tab',
        string $class = 'active'
    ): string {
        $queryParams = [];
        $urlParts = parse_url($this->request->fullUrl());

        if (is_array($urlParts) && isset($urlParts['query'])) {
            parse_str($urlParts['query'], $queryParams);
        }

        $parameter = $queryParams[$key] ?? null;
        $status = false;

        if (blank($value) && filled($segment)) {
            $status = $this->isLastUrlSegment($segment);
        } elseif (filled($value) && filled($parameter) && $this->looseEquals($parameter, $value)) {
            $status = $this->isRequestParameter($key, $value);
        }

        return $status ? " {$class} " : '';
    }

    public function isRequestOnPage(string $link, string $type = 'name'): bool
    {
        return match ($type) {
            'url' => $link === $this->request->fullUrl(),
            default => $link === $this->router->currentRouteName(),
        };
    }

    public function getCurrentRouteInfo(): object
    {
        $route = $this->router->current();

        return (object) [
            'request' => [
                'name' => $route?->getName(),
                'path' => $this->request->path(),
            ],
            'method' => $this->request->method(),
            'action' => $this->router->currentRouteAction(),
            'name' => $route?->getName(),
        ];
    }

    public function getInputValueFromQueryString(string $name): string
    {
        $value = $this->request->input($name);

        return is_string($value) ? $value : '';
    }

    public function isRequest(string $request, string $class = '', bool $returnBool = false): bool|string
    {
        $status = $this->request->is($request);

        if ($returnBool) {
            return $status;
        }

        return $status ? " {$class} " : '';
    }

    public function getRouteNameFromUrl(string $url): ?string
    {
        try {
            $request = Request::create($url);
            $route = $this->router->getRoutes()->match($request);

            return $route->getName();
        } catch (Throwable) {
            return null;
        }
    }

    public function getActiveMenuClassName(string|array $route, string $className = 'active'): string
    {
        $current = $this->router->currentRouteName();

        if (is_array($route)) {
            // Legacy used Arr::has($route, $current), which checks *keys*, not
            // values — wrong for a flat list of route names. Compare against the
            // list values instead.
            return $current !== null && in_array($current, $route, true) ? $className : '';
        }

        if ($current === $route) {
            return $className;
        }

        if (Str::contains(URL::current(), $route)) {
            return $className;
        }

        return '';
    }

    /**
     * Loose equality for request values that may arrive as scalars of mixed
     * type (e.g. the string `"5"` for an integer expectation). Scalars are
     * compared by their string form; non-scalars only match by identity.
     */
    private function looseEquals(mixed $a, mixed $b): bool
    {
        if (is_scalar($a) && is_scalar($b)) {
            return (string) $a === (string) $b;
        }

        return $a === $b;
    }
}
