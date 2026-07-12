<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\LLM\Gemini\GeminiProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMRequestException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class GeminiProviderTest extends TestCase
{
    private function provider(): GeminiProvider
    {
        return new GeminiProvider('secret-key', maxRetries: 3, retryDelay: 0);
    }

    private function fakeOk(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Hi from Gemini']]]]],
                'usageMetadata' => ['totalTokenCount' => 7],
            ], 200),
        ]);
    }

    #[Group('security')]
    public function test_api_key_is_sent_as_a_header_and_never_in_the_url(): void
    {
        $this->fakeOk();

        $response = $this->provider()->generateResponse('gemini-2.0-flash', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertSame('Hi from Gemini', $response->getContent());

        Http::assertSent(fn ($request) => $request->hasHeader('x-goog-api-key', 'secret-key')
            && !str_contains((string) $request->url(), 'key=')
            && !str_contains((string) $request->url(), 'secret-key'));
    }

    public function test_model_is_reported_from_the_request(): void
    {
        $this->fakeOk();

        $response = $this->provider()->generateResponse('gemini-2.0-flash', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertSame('gemini-2.0-flash', $response->getModel());
    }

    public function test_system_messages_become_system_instruction_and_json_mode_sets_mime_type(): void
    {
        $this->fakeOk();

        $this->provider()->generateResponse('gemini-2.0-flash', [
            ['role' => 'system', 'content' => 'You are terse.'],
            ['role' => 'user', 'content' => 'Hi'],
        ], jsonMode: true);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return ($body['systemInstruction']['parts'][0]['text'] ?? null) === 'You are terse.'
                && ($body['generationConfig']['responseMimeType'] ?? null) === 'application/json'
                // system message must not leak into contents as a role.
                && collect($body['contents'])->every(fn ($c) => $c['role'] !== 'system');
        });
    }

    public function test_invalid_base_url_scheme_is_rejected(): void
    {
        $this->expectException(LLMRequestException::class);

        new GeminiProvider('k', baseUrl: 'file:///etc/passwd');
    }

    public function test_assistant_role_is_mapped_to_model_role(): void
    {
        $this->fakeOk();

        $this->provider()->generateResponse('gemini-2.0-flash', [
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ]);

        Http::assertSent(function ($request): bool {
            $contents = $request->data()['contents'];

            return $contents[0]['role'] === 'user'
                && $contents[1]['role'] === 'model'
                && $contents[1]['parts'][0]['text'] === 'Hello';
        });
    }

    public function test_an_api_error_with_a_message_is_surfaced_in_the_exception(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'quota exceeded']], 400),
        ]);

        try {
            $this->provider()->generateResponse('gemini-2.0-flash', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertSame('Gemini API request failed (HTTP 400): quota exceeded', $e->getMessage());
            $this->assertSame(400, $e->getCode());
            $this->assertFalse($e->isRetryable());
        }

        // 4xx is non-retryable: exactly one request should have been sent.
        Http::assertSentCount(1);
    }

    public function test_an_api_error_without_a_message_falls_back_to_a_default(): void
    {
        Http::fake([
            '*' => Http::response(['unexpected' => 'shape'], 422),
        ]);

        try {
            $this->provider()->generateResponse('gemini-2.0-flash', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertSame('Gemini API request failed (HTTP 422): Gemini API request failed', $e->getMessage());
        }
    }

    public function test_a_retryable_5xx_error_is_retried_until_exhaustion(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'backend down']], 503),
        ]);

        try {
            $this->provider()->generateResponse('gemini-2.0-flash', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertStringContainsString('503', $e->getMessage());
        }

        Http::assertSentCount(3);
    }

    public function test_a_connection_failure_is_wrapped_as_a_retryable_exception(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: timed out');
        });

        try {
            $this->provider()->generateResponse('gemini-2.0-flash', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertStringStartsWith('Gemini API connection failed:', $e->getMessage());
            $this->assertTrue($e->isRetryable());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }
}
