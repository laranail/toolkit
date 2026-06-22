<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Middleware\ApiResponseMiddleware;
use Simtabi\Laranail\Toolkit\Tests\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[Group('security')]
class ApiResponseMiddlewareTest extends TestCase
{
    private function process(Response $response): Response
    {
        $middleware = new ApiResponseMiddleware();

        return $middleware->handle(Request::create('/x', 'GET'), fn () => $response);
    }

    public function test_non_json_responses_pass_through_untouched(): void
    {
        $original = new Response('<html>hi</html>', 200);

        $result = $this->process($original);

        $this->assertSame($original, $result);
        $this->assertSame('<html>hi</html>', $result->getContent());
    }

    public function test_malformed_json_body_passes_through_untouched(): void
    {
        // Force an invalid JSON body onto a JsonResponse (simulating a body that
        // was tampered with downstream). The middleware must not fatal and must
        // not corrupt it — the original bytes survive.
        $response = new JsonResponse(['ok' => true]);
        $response->setContent('{not valid json');

        $result = $this->process($response);

        $this->assertSame('{not valid json', $result->getContent());
    }

    public function test_empty_json_body_produces_envelope_without_data_key(): void
    {
        $response = new JsonResponse(null, 204);
        $response->setContent('');

        /** @var JsonResponse $result */
        $result = $this->process($response);
        $json = (array) $result->getData(true);

        $this->assertTrue($json['success']);
        $this->assertSame(204, $json['meta']['code']);
        $this->assertArrayNotHasKey('data', $json);
    }

    public function test_valid_json_is_enveloped_and_camel_cased(): void
    {
        $response = new JsonResponse(['user_id' => 1, 'nested_block' => ['inner_key' => 'v']]);

        /** @var JsonResponse $result */
        $result = $this->process($response);
        $json = (array) $result->getData(true);

        $this->assertSame(1, $json['data']['userId']);
        $this->assertSame('v', $json['data']['nestedBlock']['innerKey']);
        $this->assertSame('success', $json['meta']['status']);
    }
}
