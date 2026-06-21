<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Llm\Claude;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Simtabi\Laranail\Toolkit\Modules\Llm\LLMProviderInterface;
use Simtabi\Laranail\Toolkit\Modules\Llm\LlmRequestException;
use Simtabi\Laranail\Toolkit\Modules\Llm\RetriesHttpRequests;

class ClaudeProvider implements LLMProviderInterface
{
    use RetriesHttpRequests;

    private string $baseUrl;

    public function __construct(
        private string $apiKey,
        private int $maxRetries = 3,
        private int $retryDelay = 2,
        string $baseUrl = 'https://api.anthropic.com'
    ) {
        $this->baseUrl = $this->sanitizeBaseUrl($baseUrl);
    }

    public function generateResponse(
        string $modelName,
        array $messages,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?array $stop = null,
        ?float $topP = null,
        ?float $frequencyPenalty = null,
        ?float $presencePenalty = null,
        ?array $logitBias = null,
        ?string $user = null,
        ?bool $jsonMode = false,
        bool $fullResponse = false
    ): ClaudeResponse {
        $endpoint = $this->baseUrl . '/v1/messages';

        $payload = $this->buildPayload(
            modelName: $modelName,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stop: $stop,
            topP: $topP,
            jsonMode: $jsonMode
        );

        return $this->executeWithRetry(function () use ($endpoint, $payload, $fullResponse) {
            try {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ])->post($endpoint, $payload);
            } catch (ConnectionException $e) {
                throw new LlmRequestException('Claude API connection failed: ' . $e->getMessage(), retryable: true, previous: $e);
            }

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->json();
                $message = (is_array($body) ? ($body['error']['message'] ?? null) : null) ?? 'Claude API request failed';

                throw new LlmRequestException(
                    "Claude API request failed (HTTP {$status}): {$message}",
                    retryable: $this->isRetryableStatus($status),
                    status: $status,
                );
            }

            $data = $response->json();

            $content = '';
            if (isset($data['content'][0]['text'])) {
                $content = $data['content'][0]['text'];
            }

            return new ClaudeResponse(
                content: $content,
                model: $data['model'] ?? null,
                usage: (object) ($data['usage'] ?? []),
                rawResponse: $fullResponse ? (object) $data : null
            );
        }, 'Claude');
    }

    private function buildPayload(
        string $modelName,
        array $messages,
        ?float $temperature,
        ?int $maxTokens,
        ?array $stop,
        ?float $topP,
        ?bool $jsonMode
    ): array {
        $payload = [
            'model' => $modelName,
            'messages' => $messages,
            'max_tokens' => $maxTokens ?? 1024,
        ];

        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        if ($topP !== null) {
            $payload['top_p'] = $topP;
        }

        if ($stop !== null && !empty($stop)) {
            $payload['stop_sequences'] = $stop;
        }

        // Claude doesn't support frequency_penalty and presence_penalty like OpenAI
        // These parameters are ignored for Claude

        return $payload;
    }
}
