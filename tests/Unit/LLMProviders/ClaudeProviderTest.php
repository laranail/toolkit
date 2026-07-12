<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\LLM\Claude\ClaudeProvider;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMRequestException;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ClaudeProviderTest extends TestCase
{
    private function provider(): ClaudeProvider
    {
        // retryDelay = 0 so retries don't sleep in tests.
        return new ClaudeProvider('test-key', maxRetries: 3, retryDelay: 0);
    }

    public function test_it_parses_a_successful_response_and_sends_the_api_key_header(): void
    {
        Http::fake([
            '*' => Http::response([
                'model' => 'claude-3-5-sonnet',
                'content' => [['type' => 'text', 'text' => 'Hello there']],
                'usage' => ['input_tokens' => 5],
            ], 200),
        ]);

        $response = $this->provider()->generateResponse('claude-3-5-sonnet', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertSame('Hello there', $response->getContent());

        Http::assertSent(fn ($request) => $request->hasHeader('x-api-key', 'test-key')
            && str_ends_with((string) $request->url(), '/v1/messages'));
    }

    #[Group('security')]
    public function test_it_fails_fast_on_4xx_without_retrying(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'invalid api key']], 401),
        ]);

        try {
            $this->provider()->generateResponse('claude-3-5-sonnet', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertStringContainsString('401', $e->getMessage());
        }

        // 4xx is non-retryable: exactly one request should have been sent.
        Http::assertSentCount(1);
    }

    public function test_it_retries_on_5xx_then_gives_up(): void
    {
        Http::fake([
            '*' => Http::response(['error' => ['message' => 'overloaded']], 503),
        ]);

        $this->expectException(LLMRequestException::class);

        try {
            $this->provider()->generateResponse('claude-3-5-sonnet', [['role' => 'user', 'content' => 'Hi']]);
        } finally {
            Http::assertSentCount(3); // maxRetries attempts
        }
    }

    public function test_optional_generation_parameters_are_added_to_the_payload(): void
    {
        Http::fake([
            '*' => Http::response([
                'model' => 'claude-3-5-sonnet',
                'content' => [['type' => 'text', 'text' => 'ok']],
            ], 200),
        ]);

        $this->provider()->generateResponse(
            'claude-3-5-sonnet',
            [['role' => 'user', 'content' => 'Hi']],
            temperature: 0.25,
            maxTokens: 256,
            stop: ['END'],
            topP: 0.9,
        );

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['model'] === 'claude-3-5-sonnet'
                && $body['max_tokens'] === 256
                && $body['temperature'] === 0.25
                && $body['top_p'] === 0.9
                && $body['stop_sequences'] === ['END'];
        });
    }

    public function test_optional_parameters_are_omitted_when_not_supplied(): void
    {
        Http::fake([
            '*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]], 200),
        ]);

        // No temperature/topP and an empty stop array → those keys are omitted,
        // and max_tokens defaults to 1024.
        $this->provider()->generateResponse('claude-3-5-sonnet', [
            ['role' => 'user', 'content' => 'Hi'],
        ], stop: []);

        Http::assertSent(function ($request): bool {
            $body = $request->data();

            return $body['max_tokens'] === 1024
                && !array_key_exists('temperature', $body)
                && !array_key_exists('top_p', $body)
                && !array_key_exists('stop_sequences', $body);
        });
    }

    public function test_a_connection_failure_is_wrapped_as_a_retryable_exception(): void
    {
        Http::fake(function (): void {
            throw new ConnectionException('cURL error 28: timed out');
        });

        try {
            $this->provider()->generateResponse('claude-3-5-sonnet', [['role' => 'user', 'content' => 'Hi']]);
            $this->fail('Expected LLMRequestException.');
        } catch (LLMRequestException $e) {
            $this->assertStringStartsWith('Claude API connection failed:', $e->getMessage());
            $this->assertTrue($e->isRetryable());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }
}
