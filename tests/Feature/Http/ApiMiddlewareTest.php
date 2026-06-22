<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Pagination\LengthAwarePaginator;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('http')]
class ApiMiddlewareTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // api.request snake-cases incoming keys before the handler sees them.
        $router->post(
            '/_test/api/request',
            fn () => response()->json(['received' => request()->all()]),
        )->middleware('api.request');

        // api.response envelopes + camelCases the outgoing JSON.
        $router->get(
            '/_test/api/response',
            fn () => response()->json(['user_id' => 7, 'display_name' => 'Jane']),
        )->middleware('api.response');

        $router->get('/_test/api/response/paginated', function () {
            $paginator = new LengthAwarePaginator(
                items: [['item_id' => 1], ['item_id' => 2]],
                total: 10,
                perPage: 2,
                currentPage: 1,
            );

            return response()->json($paginator);
        })->middleware('api.response');

        $router->get(
            '/_test/api/response/error',
            fn () => response()->json(['error_code' => 'nope'], 422),
        )->middleware('api.response');
    }

    public function test_request_middleware_snake_cases_incoming_keys(): void
    {
        $response = $this->postJson('/_test/api/request', [
            'firstName' => 'Jane',
            'profileData' => ['lastSeenAt' => '2026-01-01'],
        ]);

        $received = $response->json('received');

        $this->assertArrayHasKey('first_name', $received);
        $this->assertArrayHasKey('profile_data', $received);
        $this->assertArrayHasKey('last_seen_at', $received['profile_data']);
        $this->assertSame('Jane', $received['first_name']);
    }

    public function test_response_middleware_envelopes_and_camel_cases_data(): void
    {
        $response = $this->getJson('/_test/api/response');

        $response->assertOk();
        $json = $response->json();

        // Shape is compatible with ApiResponseTrait: success/message/data/meta.
        $this->assertTrue($json['success']);
        $this->assertSame('OK', $json['message']);
        $this->assertSame(200, $json['meta']['code']);
        $this->assertSame('success', $json['meta']['status']);

        // Data keys are camelCased.
        $this->assertSame(7, $json['data']['userId']);
        $this->assertSame('Jane', $json['data']['displayName']);
    }

    public function test_response_middleware_builds_pagination_block(): void
    {
        $response = $this->getJson('/_test/api/response/paginated');

        $response->assertOk();
        $json = $response->json();

        $this->assertSame(10, $json['meta']['pagination']['total']);
        $this->assertSame(2, $json['meta']['pagination']['count']);
        $this->assertSame(2, $json['meta']['pagination']['per_page']);
        $this->assertSame(1, $json['meta']['pagination']['current_page']);
        $this->assertSame(5, $json['meta']['pagination']['total_pages']);

        // Items are unwrapped into `data` with camelCased keys.
        $this->assertSame(1, $json['data'][0]['itemId']);
        $this->assertSame(2, $json['data'][1]['itemId']);
    }

    public function test_response_middleware_marks_error_status(): void
    {
        $response = $this->getJson('/_test/api/response/error');

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertFalse($json['success']);
        $this->assertSame('error', $json['meta']['status']);
        $this->assertSame(422, $json['meta']['code']);
    }
}
