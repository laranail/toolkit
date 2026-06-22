<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Http;

use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Http\Requests\BaseRequest;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class SanitizingRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string',
            'email' => 'nullable|string',
            'username' => 'nullable|string',
            'website_url' => 'nullable|string',
            'db_table' => 'nullable|string',
            'bio' => 'nullable|string',
        ];
    }
}

#[Group('security')]
class BaseRequestTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        // Return the sanitized input the handler actually sees.
        $router->post(
            '/_test/base-request',
            fn (SanitizingRequest $request) => response()->json($request->all()),
        );
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function submit(array $payload): array
    {
        return (array) $this->postJson('/_test/base-request', $payload)->json();
    }

    public function test_html_and_script_tags_are_stripped_from_input(): void
    {
        $out = $this->submit([
            'bio' => '<script>alert(1)</script>Hello <b>world</b>',
        ]);

        $this->assertSame('alert(1)Hello world', $out['bio']);
        $this->assertStringNotContainsString('<script>', $out['bio']);
        $this->assertStringNotContainsString('<b>', $out['bio']);
    }

    public function test_script_tags_in_name_fields_are_stripped(): void
    {
        $out = $this->submit([
            'first_name' => 'Jane<script>alert("x")</script>',
        ]);

        $this->assertStringNotContainsString('<', $out['first_name']);
        $this->assertStringNotContainsString('script', $out['first_name']);
        // The name normaliser also drops the parentheses/quotes punctuation noise.
        $this->assertSame('Janealertx', $out['first_name']);
    }

    public function test_international_names_are_preserved(): void
    {
        // Accents, apostrophes, hyphens, and non-Latin scripts must survive.
        $names = [
            'José' => 'José',
            "O'Brien" => "O'Brien",
            'Müller' => 'Müller',
            'Renée-Claire' => 'Renée-Claire',
            'Đặng' => 'Đặng',
            'Светлана' => 'Светлана',
            '李明' => '李明',
        ];

        foreach ($names as $input => $expected) {
            $out = $this->submit(['first_name' => $input]);

            $this->assertSame(
                $expected,
                $out['first_name'],
                "International name '{$input}' must not be corrupted by sanitization.",
            );
        }
    }

    public function test_field_specific_normalisation_is_applied(): void
    {
        $out = $this->submit([
            'email' => '  USER@Example.COM  ',
            'username' => 'Jöhn Doe!!',
        ]);

        // email → lowercased + trimmed; username → ascii handle only.
        $this->assertSame('user@example.com', $out['email']);
        $this->assertSame('jhndoe', $out['username']);
    }
}
