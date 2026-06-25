<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\LLMProviders;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Modules\Llm\Gemini\GeminiProvider;
use Simtabi\Laranail\Toolkit\Modules\Llm\LLMRequestException;
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
}
