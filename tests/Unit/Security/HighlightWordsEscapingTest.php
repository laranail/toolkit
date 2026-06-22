<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Security;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * The highlightWords macro emits HTML; it must escape the input text and only
 * wrap matched terms in <mark>, never echo raw user input.
 */
#[Group('security')]
class HighlightWordsEscapingTest extends TestCase
{
    public function test_script_payload_in_text_is_escaped(): void
    {
        $payload = 'hello <script>alert("xss")</script> world';

        $result = (string) Str::highlightWords($payload, 'hello');

        // The script tag must be escaped, never emitted verbatim.
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
        // The only allowed raw markup is the <mark> wrapper around the term.
        $this->assertStringContainsString('<mark>hello</mark>', $result);
    }

    public function test_quotes_and_ampersands_are_escaped(): void
    {
        $result = (string) Str::highlightWords('a "quoted" & sign', 'sign');

        $this->assertStringNotContainsString('"quoted"', $result);
        $this->assertStringContainsString('&quot;quoted&quot;', $result);
        $this->assertStringContainsString('&amp;', $result);
        $this->assertStringContainsString('<mark>sign</mark>', $result);
    }

    public function test_html_in_search_term_does_not_inject_markup(): void
    {
        // A term containing HTML-special chars must match the escaped text and
        // must not introduce unescaped markup.
        $result = (string) Str::highlightWords('value <b>x</b> end', '<b>x</b>');

        $this->assertInstanceOf(HtmlString::class, Str::highlightWords('x', 'x'));
        $this->assertStringNotContainsString('<b>x</b>', $result);
        $this->assertStringContainsString('<mark>&lt;b&gt;x&lt;/b&gt;</mark>', $result);
    }

    public function test_empty_terms_still_escape_the_text(): void
    {
        $result = (string) Str::highlightWords('<i>raw</i>', '');

        $this->assertSame('&lt;i&gt;raw&lt;/i&gt;', $result);
    }
}
