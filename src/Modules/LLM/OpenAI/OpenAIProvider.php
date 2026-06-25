<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\LLM\OpenAI;

use Illuminate\Support\Facades\Log;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\ErrorException;
use Simtabi\Laranail\Toolkit\Modules\LLM\LLMProviderInterface;

final readonly class OpenAIProvider implements LLMProviderInterface
{
    private ClientContract $client;

    public function __construct(
        string $apiKey,
        private int $maxRetries = 3,
        private int $retryDelay = 2,
        ?ClientContract $client = null
    ) {
        $this->client = $client ?? \OpenAI::client($apiKey);
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
    ): OpenAIResponse {
        $parameters = $this->buildParameters(
            modelName: $modelName,
            messages: $messages,
            temperature: $temperature,
            maxTokens: $maxTokens,
            stop: $stop,
            topP: $topP,
            frequencyPenalty: $frequencyPenalty,
            presencePenalty: $presencePenalty,
            logitBias: $logitBias,
            user: $user,
            jsonMode: $jsonMode
        );

        return $this->executeWithRetry(function () use ($parameters, $fullResponse) {
            $response = $this->client->chat()->create($parameters);

            return $this->createResponse($response, $fullResponse);
        });
    }

    /**
     * Build the parameters array for the OpenAI API request
     */
    private function buildParameters(
        string $modelName,
        array $messages,
        ?float $temperature,
        ?int $maxTokens,
        ?array $stop,
        ?float $topP,
        ?float $frequencyPenalty,
        ?float $presencePenalty,
        ?array $logitBias,
        ?string $user,
        ?bool $jsonMode
    ): array {
        $parameters = [
            'model' => $modelName,
            'messages' => $messages,
        ];

        $optionalParameters = [
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stop' => $stop,
            'top_p' => $topP,
            'frequency_penalty' => $frequencyPenalty,
            'presence_penalty' => $presencePenalty,
            'logit_bias' => $logitBias,
            'user' => $user,
        ];

        foreach ($optionalParameters as $key => $value) {
            if ($value !== null) {
                $parameters[$key] = $value;
            }
        }

        if ($jsonMode) {
            $parameters['response_format'] = ['type' => 'json_object'];
        }

        return $parameters;
    }

    /**
     * Create an OpenAIResponse object from the API response
     */
    private function createResponse(object $response, bool $fullResponse): OpenAIResponse
    {
        if ($fullResponse) {
            return new OpenAIResponse(
                content: $response->choices[0]->message->content,
                model: $response->model,
                usage: $response->usage,
                rawResponse: $response
            );
        }

        return new OpenAIResponse(
            content: $response->choices[0]->message->content
        );
    }

    /**
     * Execute a function with retry logic
     *
     * @template T
     *
     * @param callable(): T $callback
     *
     * @throws ErrorException
     *
     * @return T
     */
    private function executeWithRetry(callable $callback)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxRetries) {
            try {
                return $callback();
            } catch (ErrorException $e) {
                $lastException = $e;
                $attempt++;

                if ($attempt < $this->maxRetries) {
                    Log::warning('OpenAI API request failed, retrying...', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    sleep($this->retryDelay);
                }
            }
        }

        Log::error("OpenAI API request failed after {$this->maxRetries} attempts", [
            'error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new \RuntimeException('OpenAI API request failed.');
    }
}
