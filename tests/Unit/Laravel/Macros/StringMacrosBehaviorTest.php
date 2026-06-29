<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Laravel\Macros;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

/**
 * Exhaustive behaviour tests for {@see \Simtabi\Laranail\Toolkit\Macros\StringMacros}
 * — exact assertions over the Str macros (incl. the arithmetic-heavy similarity and
 * truncation helpers) plus the Stringable mirrors, to kill surviving mutants.
 */
final class StringMacrosBehaviorTest extends TestCase
{
    public function test_case_conversions(): void
    {
        $this->assertSame('Hello World', Str::kebabToTitle('hello-world'));
        $this->assertSame('Hello World', Str::snakeToTitle('hello_world'));
        $this->assertSame('Hello World', Str::camelToTitle('helloWorld'));
    }

    public function test_truncate_middle(): void
    {
        $this->assertSame('short', Str::truncateMiddle('short', 50));
        $this->assertSame('abcde', Str::truncateMiddle('abcde', 5)); // exactly at limit → unchanged
        $this->assertSame('ab...yz', Str::truncateMiddle('abcdefghijklmnopqrstuvwxyz', 7));
        $this->assertSame('abc...xyz', Str::truncateMiddle('abcdefghijklmnopqrstuvwxyz', 9));
    }

    public function test_is_email(): void
    {
        $this->assertTrue(Str::isEmail('user@example.com'));
        $this->assertFalse(Str::isEmail('not-an-email'));
        $this->assertFalse(Str::isEmail(''));
    }

    public function test_whitespace_helpers(): void
    {
        $this->assertSame('abc', Str::stripWhitespace("a b\tc"));
        $this->assertSame('a b c', Str::normalizeWhitespace("  a   b\nc  "));
    }

    public function test_to_bool_truthy_set_is_exact(): void
    {
        foreach (['1', 'true', 'YES', ' on '] as $truthy) {
            $this->assertTrue(Str::toBool($truthy), $truthy);
        }
        foreach (['0', 'false', 'no', 'off', '', 'maybe', '2'] as $falsey) {
            $this->assertFalse(Str::toBool($falsey), $falsey);
        }
    }

    public function test_wrap_with(): void
    {
        $this->assertSame('"x"', Str::wrapWith('x'));
        $this->assertSame('|x|', Str::wrapWith('x', '|')); // same wrapper added both sides
        $this->assertSame('**x**', Str::wrapWith('x', '**'));
    }

    public function test_replace_many_applies_in_order(): void
    {
        $this->assertSame('1-2-3', Str::replaceMany('a-b-c', ['a' => 1, 'b' => 2, 'c' => 3]));
        $this->assertSame('abc', Str::replaceMany('abc', []));
    }

    public function test_matches_full_pcre(): void
    {
        $this->assertTrue(Str::matches('abc123', '/^[a-z]+\d+$/'));
        $this->assertFalse(Str::matches('abc', '/^\d+$/'));
    }

    public function test_reverse_and_word_count(): void
    {
        $this->assertSame('cba', Str::reverseString('abc'));
        $this->assertSame(3, Str::countWords('one two three'));
        $this->assertSame(0, Str::countWords(''));
    }

    public function test_remove_accents(): void
    {
        // Transliteration output is platform/iconv-dependent, so assert the stable
        // contract: ASCII is unchanged, and accented input becomes pure ASCII.
        $this->assertSame('Hello', Str::removeAccents('Hello'));
        $this->assertMatchesRegularExpression('/^[\x00-\x7F]+$/', Str::removeAccents('Crème Brûlée'));
    }

    public function test_reading_minutes_rounds_up_and_floors_at_one(): void
    {
        $this->assertSame(1, Str::readingMinutes('just a few words'));
        $this->assertSame(1, Str::readingMinutes(''));               // floored to 1
        $this->assertSame(2, Str::readingMinutes(str_repeat('word ', 300))); // 300/200 → ceil → 2
        $this->assertSame(3, Str::readingMinutes(str_repeat('word ', 300), 120)); // 300/120 → ceil 3
    }

    public function test_highlight_words_escapes_and_marks(): void
    {
        $result = Str::highlightWords('hello world', 'world');
        $this->assertInstanceOf(HtmlString::class, $result);
        $this->assertSame('hello <mark>world</mark>', (string) $result);
        // no terms → escaped, unmarked
        $this->assertSame('a &lt;b&gt;', (string) Str::highlightWords('a <b>', []));
    }

    public function test_strip_tags_with_and_without_allowlist(): void
    {
        $this->assertSame('hi there', Str::stripTags('<p>hi <b>there</b></p>'));
        $this->assertSame('hi <b>there</b>', Str::stripTags('<p>hi <b>there</b></p>', '<b>'));
    }

    public function test_lines_count(): void
    {
        $this->assertSame(0, Str::linesCount(''));
        $this->assertSame(1, Str::linesCount('one line'));
        $this->assertSame(3, Str::linesCount("a\nb\r\nc"));
    }

    public function test_interpolate_replaces_longest_key_first(): void
    {
        $this->assertSame(
            'Hi Ada (Ada Lovelace)',
            Str::interpolate('Hi :name (:name_full)', ['name' => 'Ada', 'name_full' => 'Ada Lovelace']),
        );
    }

    public function test_levenshtein_and_similar_text(): void
    {
        $this->assertSame(3, Str::levenshtein('kitten', 'sitting'));
        $this->assertSame(0, Str::levenshtein('same', 'same'));
        $this->assertSame(100.0, Str::similarText('same', 'same'));
        $this->assertSame(0.0, Str::similarText('abc', 'xyz'));
    }

    public function test_jaro_winkler_known_vectors(): void
    {
        $this->assertSame(1.0, Str::jaroWinkler('', ''));
        $this->assertSame(0.0, Str::jaroWinkler('', 'abc'));
        $this->assertSame(1.0, Str::jaroWinkler('abc', 'abc'));
        $this->assertSame(0.0, Str::jaroWinkler('abc', 'xyz'));
        $this->assertSame(0.9611, Str::jaroWinkler('martha', 'marhta'));
    }

    public function test_closest_by_levenshtein(): void
    {
        $this->assertSame('cat', Str::closest('bat', ['dog', 'cat', 'car']));
        $this->assertNull(Str::closest('x', []));
    }

    public function test_stringable_mirrors_delegate(): void
    {
        $this->assertSame('Hello World', (string) Str::of('hello-world')->kebabToTitle());
        $this->assertSame('Hello World', (string) Str::of('hello_world')->snakeToTitle());
        $this->assertSame('Hello World', (string) Str::of('helloWorld')->camelToTitle());
        $this->assertSame('ab...yz', (string) Str::of('abcdefghijklmnopqrstuvwxyz')->truncateMiddle(7));
        $this->assertTrue(Str::of('user@example.com')->isEmail());
        $this->assertSame('abc', (string) Str::of("a b\tc")->stripWhitespace());
        $this->assertSame('a b c', (string) Str::of("  a   b\nc ")->normalizeWhitespace());
        $this->assertTrue(Str::of('yes')->toBool());
        $this->assertSame('"x"', (string) Str::of('x')->wrapWith());
        $this->assertTrue(Str::of('abc123')->matches('/^[a-z]+\d+$/'));
        $this->assertSame('cba', (string) Str::of('abc')->reverseString());
        $this->assertSame(3, Str::of('one two three')->countWords());
        $this->assertMatchesRegularExpression('/^[\x00-\x7F]+$/', (string) Str::of('éèà')->removeAccents());
        $this->assertSame(1, Str::of('few words')->readingMinutes());
        $this->assertInstanceOf(HtmlString::class, Str::of('hello world')->highlightWords('world'));
        $this->assertSame(3, Str::of("a\nb\r\nc")->linesCount());
        $this->assertSame('Hi Ada', (string) Str::of('Hi :n')->interpolate(['n' => 'Ada']));
        $this->assertSame(3, Str::of('kitten')->levenshtein('sitting'));
        $this->assertSame(100.0, Str::of('same')->similarText('same'));
        $this->assertSame(1.0, Str::of('abc')->jaroWinkler('abc'));
        $this->assertSame('cat', Str::of('bat')->closest(['dog', 'cat']));
        $this->assertInstanceOf(Stringable::class, Str::of('x')->kebabToTitle());
    }
}
