<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Modules\Llm;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Toolkit\Modules\Llm\Claude\ClaudeProvider;
use Simtabi\Laranail\Toolkit\Modules\Llm\Gemini\GeminiProvider;
use Simtabi\Laranail\Toolkit\Modules\Llm\OpenAI\OpenAIProvider;
use Simtabi\Laranail\Toolkit\Support\Config as ToolkitConfig;

/**
 * Deferred service provider for the self-contained LLM module.
 *
 * Resolves {@see LLMProviderInterface} to the configured default driver
 * (OpenAI / Gemini / Claude), keyed off `laranail.toolkit.llm.default_provider`.
 * Kept self-contained so the module can later be extracted into its own package.
 */
class LlmServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        // Register base LLM Provider interface with provider selection
        $this->app->bind(LLMProviderInterface::class, function (Application $app): LLMProviderInterface {
            $default = config('laranail.toolkit.llm.default_provider', 'openai');

            if ($default === 'gemini') {
                return new GeminiProvider(
                    apiKey: ToolkitConfig::string('laranail.toolkit.gemini.api_key'),
                    maxRetries: ToolkitConfig::int('laranail.toolkit.gemini.max_retries', 3),
                    retryDelay: ToolkitConfig::int('laranail.toolkit.gemini.retry_delay', 2),
                    baseUrl: ToolkitConfig::string('laranail.toolkit.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta')
                );
            }

            if ($default === 'claude') {
                return new ClaudeProvider(
                    apiKey: ToolkitConfig::string('laranail.toolkit.claude.api_key'),
                    maxRetries: ToolkitConfig::int('laranail.toolkit.claude.max_retries', 3),
                    retryDelay: ToolkitConfig::int('laranail.toolkit.claude.retry_delay', 2),
                    baseUrl: ToolkitConfig::string('laranail.toolkit.claude.base_url', 'https://api.anthropic.com')
                );
            }

            return new OpenAIProvider(
                apiKey: ToolkitConfig::string('laranail.toolkit.openai.api_key'),
                maxRetries: ToolkitConfig::int('laranail.toolkit.openai.max_retries', 3),
                retryDelay: ToolkitConfig::int('laranail.toolkit.openai.retry_delay', 2)
            );
        });

        $this->app->alias(LLMProviderInterface::class, 'laranail.llm');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            LLMProviderInterface::class,
            'laranail.llm',
        ];
    }
}
