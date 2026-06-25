<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Support;

use Simtabi\Laranail\Toolkit\Support\ApiResponder;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ApiResponderTest extends TestCase
{
    public function test_success_builds_the_canonical_envelope(): void
    {
        $response = new ApiResponder()->success(
            data: ['id' => 1],
            message: 'Fetched.',
            statusCode: 201,
            meta: ['page' => 1],
        );

        $this->assertSame(201, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Fetched.', $payload['message']);
        $this->assertSame(['id' => 1], $payload['data']);
        $this->assertSame(['page' => 1], $payload['meta']);
    }

    public function test_error_builds_the_canonical_envelope(): void
    {
        $response = new ApiResponder()->error(
            message: 'Validation failed.',
            statusCode: 422,
            errors: ['email' => ['required']],
        );

        $this->assertSame(422, $response->getStatusCode());

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertSame('Validation failed.', $payload['message']);
        $this->assertSame(['email' => ['required']], $payload['errors']);
    }

    public function test_defaults_match_the_trait(): void
    {
        $success = new ApiResponder()->success();
        $this->assertSame(200, $success->getStatusCode());
        $this->assertSame('Request successful.', $success->getData(true)['message']);

        $error = new ApiResponder()->error();
        $this->assertSame(500, $error->getStatusCode());
        $this->assertFalse($error->getData(true)['success']);
    }
}
