<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

use Simtabi\Laranail\Toolkit\Modules\LLM\Claude\ClaudeResponse;
use Simtabi\Laranail\Toolkit\Modules\LLM\Gemini\GeminiResponse;
use Simtabi\Laranail\Toolkit\Modules\LLM\OpenAI\OpenAIResponse;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class ResponseDtosTest extends TestCase
{
    public function test_openai_response_exposes_all_accessors(): void
    {
        $usage = (object) ['prompt_tokens' => 1];
        $raw = (object) ['id' => 'x'];

        $response = new OpenAIResponse('hi', 'gpt-4o', $usage, $raw);

        $this->assertSame('hi', $response->getContent());
        $this->assertSame('gpt-4o', $response->getModel());
        $this->assertSame($usage, $response->getUsage());
        $this->assertSame($raw, $response->getRawResponse());

        $array = $response->toArray();
        $this->assertSame('hi', $array['content']);
        $this->assertSame('gpt-4o', $array['model']);
        $this->assertSame($usage, $array['usage']);

        $this->assertSame($array, $response->jsonSerialize());
        $this->assertJson($response->toJson());
    }

    public function test_openai_response_defaults_are_null(): void
    {
        $response = new OpenAIResponse('only content');

        $this->assertNull($response->getModel());
        $this->assertNull($response->getUsage());
        $this->assertNull($response->getRawResponse());
    }

    public function test_claude_response_exposes_all_accessors(): void
    {
        $usage = (object) ['input_tokens' => 3];
        $raw = (object) ['type' => 'message'];

        $response = new ClaudeResponse('claude body', 'claude-3-5-sonnet', $usage, $raw);

        $this->assertSame('claude body', $response->getContent());
        $this->assertSame('claude-3-5-sonnet', $response->getModel());
        $this->assertSame($usage, $response->getUsage());
        $this->assertSame($raw, $response->getRawResponse());

        $array = $response->toArray();
        $this->assertSame('claude body', $array['content']);
        $this->assertSame($array, $response->jsonSerialize());
        $this->assertJson($response->toJson());
    }

    public function test_gemini_response_exposes_all_accessors(): void
    {
        $usage = (object) ['totalTokenCount' => 9];
        $raw = (object) ['candidates' => []];

        $response = new GeminiResponse('gemini body', 'gemini-1.5-pro', $usage, $raw);

        $this->assertSame('gemini body', $response->getContent());
        $this->assertSame('gemini-1.5-pro', $response->getModel());
        $this->assertSame($usage, $response->getUsage());
        $this->assertSame($raw, $response->getRawResponse());

        $array = $response->toArray();
        $this->assertSame('gemini body', $array['content']);
        $this->assertSame($array, $response->jsonSerialize());
        $this->assertJson($response->toJson());
    }
}
