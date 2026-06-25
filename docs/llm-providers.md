# LLM providers

A single contract, `LLMProviderInterface`, abstracts three drivers — OpenAI,
Claude, and Gemini. The driver bound in the container is chosen by
`config('laranail.toolkit.llm.default_provider')` (`openai` by default).

## Contract

```php
namespace Simtabi\Laranail\Toolkit\Modules\Llm;

interface LLMProviderInterface
{
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
        bool $fullResponse = false,
    );
}
```

`$messages` follows the chat format: `[['role' => 'user', 'content' => '...']]`.

## Usage

Inject the contract; the configured provider is resolved for you:

```php
use Simtabi\Laranail\Toolkit\Modules\Llm\LLMProviderInterface;

public function __construct(private LLMProviderInterface $llm) {}

$response = $this->llm->generateResponse(
    modelName: 'gpt-4o-mini',
    messages: [
        ['role' => 'system', 'content' => 'You are concise.'],
        ['role' => 'user', 'content' => 'Summarize Laravel queues.'],
    ],
    temperature: 0.4,
    maxTokens: 200,
);

echo $response->getContent();
```

The module is wired by the deferred `Modules\Llm\LlmServiceProvider`, which binds
`LLMProviderInterface` to the configured default driver. A `LLM` facade
(alias `LLM`) fronts the same resolved provider for quick, non-injected calls:

```php
use Simtabi\Laranail\Toolkit\Modules\Llm\LLM;

$response = LLM::generateResponse(modelName: 'gpt-4o-mini', messages: [
    ['role' => 'user', 'content' => 'Summarize Laravel queues.'],
]);
```

Pass `jsonMode: true` to request a JSON object (OpenAI sets
`response_format = {type: json_object}`), and `fullResponse: true` to populate
the response object's `model`, `usage`, and raw payload.

## Response objects

Each driver returns its own response object — `OpenAIResponse`,
`ClaudeResponse`, `GeminiResponse` — all with the same shape:

| Member | |
|--------|---|
| `getContent(): string` | The generated text. |
| `getModel(): ?string` | Model id (when `fullResponse`). |
| `getUsage(): ?object` | Token usage (when `fullResponse`). |
| `getRawResponse(): ?object` | The underlying SDK/HTTP payload. |
| `toArray()` / `toJson()` / `jsonSerialize()` | Serialization helpers. |

## Drivers

| Provider | Constructor | Default model (config) |
|----------|-------------|------------------------|
| OpenAI | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2)` | `gpt-3.5-turbo` |
| Claude | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2, string $baseUrl = 'https://api.anthropic.com')` | `claude-3-5-sonnet-20241022` |
| Gemini | `__construct(string $apiKey, int $maxRetries = 3, int $retryDelay = 2, string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta')` | `gemini-2.0-flash` |

OpenAI uses the `openai-php/client` SDK. Claude and Gemini call their HTTP APIs
directly through the shared `RetriesHttpRequests` concern.

You can also resolve a specific driver directly when you need to bypass the
configured default:

```php
use Simtabi\Laranail\Toolkit\Modules\Llm\Claude\ClaudeProvider;

$claude = new ClaudeProvider(apiKey: config('laranail.toolkit.claude.api_key'));
```

## Retries & errors

Requests are retried on transient failures up to `max_retries`, sleeping
`retry_delay` seconds between attempts. The shared HTTP concern retries only on
retryable statuses (HTTP 429 and 5xx) and fails fast on 4xx. A non-retryable or
exhausted failure surfaces as `LLMRequestException`, whose `isRetryable()`
reports whether the failure was transient. `RetriesHttpRequests::sanitizeBaseUrl()`
rejects non-HTTP(S) base URLs.

## Configuration

See [configuration](configuration.md) for the full key reference. At minimum set
the API key and (optionally) the default provider:

```dotenv
LLM_DEFAULT_PROVIDER=openai
OPENAI_API_KEY=sk-...
```

[← Docs index](../README.md#documentation)
