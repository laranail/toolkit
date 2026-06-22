<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Feature\Laravel\Blade;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * XSS-hardening assertions for the value-echoing Blade directives.
 *
 * Every directive that flows a runtime *value* into HTML must escape it; a
 * `<script>`/`"` payload in the value must never reach the output un-escaped.
 */
#[Group('blade')]
#[Group('security')]
class BladeDirectivesSecurityTest extends TestCase
{
    public function test_nl2br_escapes_html_in_its_value(): void
    {
        $out = Blade::render('@nl2br($text)', ['text' => "<script>alert(1)</script>\nx"]);

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        // Only the directive's own <br> remains as raw markup.
        $this->assertStringContainsString('<br />', $out);
    }

    public function test_data_attributes_escapes_keys_and_values(): void
    {
        $out = Blade::render('@dataAttributes($attrs)', [
            'attrs' => ['x' => '"><script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&quot;', $out);
    }

    public function test_inputvalue_escapes_model_value(): void
    {
        $model = (object) ['title' => '"><script>alert(1)</script>'];

        $out = Blade::render("@inputvalue(\$m, 'title')", ['m' => $model]);

        $this->assertStringNotContainsString('<script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&quot;', $out);
    }
}
