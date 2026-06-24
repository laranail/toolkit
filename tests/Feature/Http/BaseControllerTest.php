<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\Toolkit\Http\Controllers\BaseController;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class BaseControllerFixture extends BaseController
{
    public function ok(): JsonResponse
    {
        return $this->successResponse(['id' => 1], 'done');
    }

    public function bad(): JsonResponse
    {
        return $this->errorResponse('nope', 422, ['field' => ['bad']]);
    }
}

class BaseControllerTest extends TestCase
{
    public function test_success_response_helper_is_inherited(): void
    {
        $response = new BaseControllerFixture()->ok();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'done',
            'data' => ['id' => 1],
            'meta' => [],
        ], $response->getData(true));
    }

    public function test_error_response_helper_is_inherited(): void
    {
        $response = new BaseControllerFixture()->bad();

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame([
            'success' => false,
            'message' => 'nope',
            'errors' => ['field' => ['bad']],
        ], $response->getData(true));
    }

    public function test_authorization_and_validation_traits_are_available(): void
    {
        $controller = new BaseControllerFixture();

        $this->assertTrue(method_exists($controller, 'authorize'));
        $this->assertTrue(method_exists($controller, 'validate'));
    }
}
