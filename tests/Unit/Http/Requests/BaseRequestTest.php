<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Http\Requests;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Requests\BaseRequest;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class UrlDbRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'website_url' => 'nullable|string',
            'db_table' => 'nullable|string',
        ];
    }
}

class RequiredNameRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['title' => 'required|string'];
    }
}

class ForbiddenRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [];
    }
}

#[Group('security')]
class BaseRequestTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->post(
            '/_test/base-request/url-db',
            fn (UrlDbRequest $request) => response()->json($request->all()),
        );

        $router->post(
            '/_test/base-request/required',
            fn (RequiredNameRequest $request) => response()->json($request->validated()),
        );

        $router->post(
            '/_test/base-request/forbidden',
            fn (ForbiddenRequest $request) => response()->json(['ok' => true]),
        );
    }

    public function test_url_fields_have_illegal_characters_stripped(): void
    {
        $response = $this->postJson('/_test/base-request/url-db', [
            'website_url' => '  http://exam ple.com/foo bar  ',
        ]);

        // strip_tags/trim run first, then FILTER_SANITIZE_URL drops the spaces.
        $response->assertJsonPath('website_url', 'http://example.com/foobar');
    }

    public function test_db_identifier_fields_are_reduced_to_a_safe_charset(): void
    {
        $response = $this->postJson('/_test/base-request/url-db', [
            'db_table' => 'my_table; DROP TABLE users',
        ]);

        // Only [A-Za-z0-9._-] survive: spaces and the semicolon are removed.
        $response->assertJsonPath('db_table', 'my_tableDROPTABLEusers');
    }

    public function test_failed_validation_returns_a_422_with_errors(): void
    {
        $response = $this->postJson('/_test/base-request/required', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_failed_authorization_returns_a_403(): void
    {
        $response = $this->postJson('/_test/base-request/forbidden', []);

        $response->assertStatus(403);
    }
}
