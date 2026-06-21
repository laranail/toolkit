<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

use GuzzleHttp\Psr7\Response as Psr7Response;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Responses\Chat\CreateResponse;
use OpenAI\Testing\ClientFake;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Llm\OpenAI\OpenAIProvider;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class OpenAIProviderTest extends TestCase
{
    private function errorException(string $message): ErrorException
    {
        return new ErrorException(['message' => $message], new Psr7Response(500));
    }

    private function chatResponse(string $content, string $model = 'gpt-4o'): CreateResponse
    {
        return CreateResponse::fake([
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => $content],
                    'finish_reason' => 'stop',
                ],
            ],
        ]);
    }

    public function test_it_maps_a_successful_response(): void
    {
        $client = new ClientFake([$this->chatResponse('Hello from OpenAI')]);

        $provider = new OpenAIProvider('test-key', maxRetries: 3, retryDelay: 0, client: $client);

        $response = $provider->generateResponse('gpt-4o', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertSame('Hello from OpenAI', $response->getContent());
        $this->assertNull($response->getModel());
    }

    public function test_it_returns_full_response_with_model_and_usage(): void
    {
        $client = new ClientFake([$this->chatResponse('Full body', 'gpt-4o-mini')]);

        $provider = new OpenAIProvider('test-key', maxRetries: 3, retryDelay: 0, client: $client);

        $response = $provider->generateResponse(
            'gpt-4o-mini',
            [['role' => 'user', 'content' => 'Hi']],
            temperature: 0.5,
            maxTokens: 100,
            stop: ['END'],
            jsonMode: true,
            fullResponse: true,
        );

        $this->assertSame('Full body', $response->getContent());
        $this->assertSame('gpt-4o-mini', $response->getModel());
        $this->assertNotNull($response->getUsage());
        $this->assertNotNull($response->getRawResponse());
    }

    public function test_it_retries_on_error_then_succeeds(): void
    {
        $client = new ClientFake([
            $this->errorException('temporary failure'),
            $this->chatResponse('Recovered'),
        ]);

        $provider = new OpenAIProvider('test-key', maxRetries: 3, retryDelay: 0, client: $client);

        $response = $provider->generateResponse('gpt-4o', [
            ['role' => 'user', 'content' => 'Hi'],
        ]);

        $this->assertSame('Recovered', $response->getContent());
    }

    #[Group('security')]
    public function test_it_rethrows_after_exhausting_retries(): void
    {
        $client = new ClientFake([
            $this->errorException('fail 1'),
            $this->errorException('fail 2'),
            $this->errorException('fail 3'),
        ]);

        $provider = new OpenAIProvider('test-key', maxRetries: 3, retryDelay: 0, client: $client);

        $this->expectException(ErrorException::class);

        $provider->generateResponse('gpt-4o', [['role' => 'user', 'content' => 'Hi']]);
    }
}
