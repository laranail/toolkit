<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

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
}
