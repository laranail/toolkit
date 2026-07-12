<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Middleware\EmailObfuscatorMiddleware;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

#[Group('security')]
class EmailObfuscatorMiddlewareTest extends TestCase
{
    public function test_emails_are_entity_encoded_in_html_responses(): void
    {
        Route::middleware(EmailObfuscatorMiddleware::class)->get(
            '/obfuscate-html',
            static fn (): string => '<p>Reach us at hello@example.com today.</p>',
        );

        $response = $this->get('/obfuscate-html');

        $response->assertOk();
        $content = $response->getContent();

        // The raw address must no longer appear verbatim...
        $this->assertStringNotContainsString('hello@example.com', $content);
        // ...but the @ and the chars are HTML entities the browser still renders.
        $this->assertStringContainsString('&#64;', $content); // @
        $this->assertStringContainsString('today.', $content);
    }

    public function test_json_responses_are_left_untouched(): void
    {
        Route::middleware(EmailObfuscatorMiddleware::class)->get(
            '/obfuscate-json',
            static fn (): array => ['email' => 'hello@example.com'],
        );

        $response = $this->getJson('/obfuscate-json');

        $response->assertOk();
        // JSON body must keep the literal address (no entity mangling).
        $this->assertStringContainsString('hello@example.com', $response->getContent());
        $response->assertJson(['email' => 'hello@example.com']);
    }
}
