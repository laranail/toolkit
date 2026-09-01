<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Behaviour of the Response macros, which delegate to the canonical
 * ApiResponseTrait envelope (success/error/message) or stream raw PDF bytes
 * (pdf) — never emitting unescaped HTML like the legacy versions did.
 */
class ResponseMacroBehaviorTest extends TestCase
{
    public function test_success_uses_the_api_response_envelope(): void
    {
        $response = response()->success(['id' => 1], 'Created', 201, ['page' => 1]);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Created', $payload['message']);
        $this->assertSame(['id' => 1], $payload['data']);
        $this->assertSame(['page' => 1], $payload['meta']);
    }

    public function test_error_uses_the_api_response_envelope(): void
    {
        $response = response()->error('Nope', 422, ['field' => 'required']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(422, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('Nope', $payload['message']);
        $this->assertSame(['field' => 'required'], $payload['errors']);
    }

    public function test_error_defaults_to_a_bad_request(): void
    {
        $response = response()->error();

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('Bad Request', $response->getData(true)['message']);
    }

    public function test_message_returns_a_bodyless_acknowledgement(): void
    {
        $response = response()->message('Accepted', 202);

        $payload = $response->getData(true);
        $this->assertTrue($payload['success']);
        $this->assertSame('Accepted', $payload['message']);
        $this->assertNull($payload['data']);
        $this->assertSame(202, $response->getStatusCode());
    }

    public function test_pdf_streams_bytes_with_pdf_headers(): void
    {
        $inline = response()->pdf('%PDF-1.4 bytes', 'report.pdf');

        $this->assertInstanceOf(Response::class, $inline);
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));
        $this->assertSame('inline; filename="report.pdf"', $inline->headers->get('Content-Disposition'));
        $this->assertSame('%PDF-1.4 bytes', $inline->getContent());

        $download = response()->pdf('bytes', 'invoice.pdf', true);
        $this->assertSame('attachment; filename="invoice.pdf"', $download->headers->get('Content-Disposition'));
    }
}
