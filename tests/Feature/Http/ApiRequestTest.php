<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use Simtabi\Laranail\Toolkit\Http\Requests\ApiRequest;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class CreateWidgetApiRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'email'],
        ];
    }
}

class ApiRequestTest extends TestCase
{
    public function test_failed_validation_returns_a_json_error_envelope_at_422(): void
    {
        Route::post('/api/widgets', static fn (CreateWidgetApiRequest $request): array => $request->validated());

        $response = $this->postJson('/api/widgets', ['name' => '']);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'The provided data failed validation.',
        ]);
        $response->assertJsonStructure(['success', 'message', 'errors' => ['name', 'email']]);
    }

    public function test_valid_request_passes_through_with_sanitized_input(): void
    {
        Route::post('/api/widgets', static fn (CreateWidgetApiRequest $request): array => $request->validated());

        $response = $this->postJson('/api/widgets', [
            'name' => '  Widget  ',
            'email' => 'USER@EXAMPLE.COM',
        ]);

        $response->assertOk();
        // BaseRequest sanitization: trimmed name, lowercased email.
        $response->assertJson([
            'name' => 'Widget',
            'email' => 'user@example.com',
        ]);
    }
}
