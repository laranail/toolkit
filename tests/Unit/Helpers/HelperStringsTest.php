<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Toolkit\Tests\Unit\Helpers;

use Faker\Generator;
use RuntimeException;
use Simtabi\Laranail\Toolkit\Helpers\Helper;
use Simtabi\Laranail\Toolkit\Tests\TestCase;

class HelperStringsTest extends TestCase
{
    public function test_array_trim_trims_only_string_values(): void
    {
        $this->assertSame(
            ['a', 'b', 3],
            Helper::arrayTrim(['  a ', "b\n", 3]),
        );
    }

    public function test_array_flatten_collapses_nested_arrays(): void
    {
        $this->assertSame(
            [1, 2, 3, 4],
            Helper::arrayFlatten([1, [2, [3, 4]]]),
        );
    }

    public function test_str_between_extracts_the_inner_substring(): void
    {
        $this->assertSame('value', Helper::strBetween('[value]', '[', ']'));
        $this->assertNull(Helper::strBetween('no markers', '[', ']'));
    }

    public function test_str_slugify_transliterates_unicode(): void
    {
        $this->assertSame('cafe-au-lait', Helper::strSlugify('Café au lait'));
        $this->assertSame('hello-world', Helper::strSlugify('Hello, World!'));
    }

    // --- Folded-in StringHelperService delta ---

    public function test_uc_words_title_cases_multibyte(): void
    {
        $this->assertSame('Hello World', Helper::ucWords('hello world'));
        $this->assertSame('Élan Vital', Helper::ucWords('élan vital'));
    }

    public function test_username_from_email_strips_and_prefixes(): void
    {
        $this->assertSame('john.doe', Helper::usernameFromEmail('john.doe@example.com'));
        // Local part starting with a non-letter gets a user_ prefix.
        $this->assertSame('user_123abc', Helper::usernameFromEmail('123abc@example.com'));
        // Nothing usable falls back to "user".
        $this->assertSame('user', Helper::usernameFromEmail('!!!@example.com'));
    }

    public function test_email_from_username_appends_domain_or_passes_through(): void
    {
        $this->assertSame('jane@example.com', Helper::emailFromUsername('jane'));
        $this->assertSame('jane@corp.test', Helper::emailFromUsername('jane', 'corp.test'));
        // Already an email — returned unchanged.
        $this->assertSame('jane@x.com', Helper::emailFromUsername('jane@x.com'));
    }

    // --- Folded-in Username (pheg-free native generator) ---

    public function test_name_to_usernames_generates_native_candidates(): void
    {
        $candidates = Helper::nameToUsernames('Jane', 'Doe');

        $this->assertContains('janedoe', $candidates);
        $this->assertContains('jane.doe', $candidates);
        $this->assertContains('jdoe', $candidates);
        // No duplicates and no empty entries.
        $this->assertSame($candidates, array_values(array_unique($candidates)));
        $this->assertNotContains('', $candidates);
    }

    public function test_name_to_usernames_handles_missing_last_name(): void
    {
        $candidates = Helper::nameToUsernames('Madonna');

        $this->assertContains('madonna', $candidates);
        $this->assertNotEmpty($candidates);
    }

    public function test_name_to_usernames_empty_for_blank_input(): void
    {
        $this->assertSame([], Helper::nameToUsernames('', ''));
    }

    // --- G8a: folded-in UtilityService / Faker / Class helpers ---

    public function test_array_to_dot_notation_converts_brackets(): void
    {
        $this->assertSame('a.b.c', Helper::arrayToDotNotation('a[b][c]'));
        $this->assertSame('plain', Helper::arrayToDotNotation('plain'));
    }

    public function test_escape_html_escapes_strings_and_joins_arrays(): void
    {
        $this->assertSame('&lt;b&gt;hi&lt;/b&gt;', (string) Helper::escapeHtml('<b>hi</b>'));
        $this->assertSame('', (string) Helper::escapeHtml(null));
        $this->assertSame('a&amp;b', (string) Helper::escapeHtml(['a&', 'b']));
    }

    public function test_class_basename_returns_short_name(): void
    {
        $this->assertSame('Helper', Helper::classBasename(Helper::class));
        $this->assertSame('Helper', Helper::classBasename(new Helper()));
    }

    public function test_random_int_except_avoids_excluded_values(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $value = Helper::randomIntExcept(1, 3, [2]);

            $this->assertContains($value, [1, 3]);
        }
    }

    public function test_random_int_except_normalises_a_reversed_range(): void
    {
        for ($i = 0; $i < 50; $i++) {
            // from > to is swapped internally, so the result still lands in [1, 3].
            $value = Helper::randomIntExcept(3, 1, [2]);

            $this->assertContains($value, [1, 3]);
        }
    }

    public function test_random_int_except_throws_when_no_value_is_allowed(): void
    {
        $this->expectException(RuntimeException::class);

        Helper::randomIntExcept(1, 2, [1, 2]);
    }

    public function test_faker_returns_a_generator(): void
    {
        $this->assertInstanceOf(Generator::class, Helper::faker());
    }

    // --- Random handle-style username generation ---

    public function test_generate_username_is_a_handle_with_a_numeric_suffix(): void
    {
        $username = Helper::generateUsername();

        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', $username);
        // Always a valid identifier: starts with a letter, alphanumeric only.
        $this->assertMatchesRegularExpression('/^[a-z][a-z0-9]*$/', $username);
    }

    public function test_generate_username_honours_prefix_and_digits(): void
    {
        $this->assertMatchesRegularExpression('/^guest[0-9]{2}$/', Helper::generateUsername('guest', 2));
        // Non-alphabetic prefix characters are stripped; an empty prefix falls back to "user".
        $this->assertMatchesRegularExpression('/^user[0-9]{4}$/', Helper::generateUsername('123!@#'));
        $this->assertMatchesRegularExpression('/^abc[0-9]$/', Helper::generateUsername('a1b2c3', 1));
    }

    public function test_generate_username_clamps_digit_count(): void
    {
        // digits below 1 clamps to 1, above 10 clamps to 10.
        $this->assertMatchesRegularExpression('/^user[0-9]$/', Helper::generateUsername('user', 0));
        $this->assertMatchesRegularExpression('/^user[0-9]{10}$/', Helper::generateUsername('user', 99));
    }

    // --- Folded-in date helpers (carbonParse / carbonHumanDiff) ---

    public function test_carbon_parse_formats_a_date(): void
    {
        $this->assertSame('2026-01-02 00:00:00', Helper::carbonParse('2026-01-02'));
        $this->assertSame('2026-01-02', Helper::carbonParse('2026-01-02 13:45:00', 'Y-m-d'));
    }

    public function test_carbon_human_diff_is_a_relative_string(): void
    {
        $this->assertStringContainsString('ago', Helper::carbonHumanDiff('2000-01-01 00:00:00'));
    }
}
